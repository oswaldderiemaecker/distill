<?php

/*
 * This file is part of the Distill package.
 *
 * (c) Raul Fraile <raulfraile@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Distill;

use Distill\Exception\HashAlgorithmNotSupportedException;
use Distill\Exception\NotSingleDirectoryException;
use Distill\Extractor\ExtractorInterface;
use Distill\Strategy\StrategyInterface;
use Distill\Format\FormatInterface;
use Pimple\Container;
use Symfony\Component\Filesystem\Filesystem;

class Distill
{

    /**
     * Compressed file extractor.
     * @var ExtractorInterface Extractor
     */
    protected $extractor;

    /**
     * Strategy.
     * @var StrategyInterface
     */
    protected $strategy;

    /**
     * Format guesser.
     * @var FormatGuesserInterface
     */
    protected $formatGuesser;

    /**
     * Files.
     * @var File[]
     */
    protected $files;

    /**
     * Container.
     * @var Container
     */
    protected $container;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->container = new Container();
        $this->container->register(new ContainerProvider());
    }

    /**
     * Extracts the compressed file into the given path.
     * @param string                 $file   Compressed file
     * @param string                 $path   Destination path
     * @param Format\FormatInterface $format
     *
     * @return bool Returns TRUE when successful, FALSE otherwise
     */
    public function extract($file, $path, FormatInterface $format = null)
    {
        if (null === $format) {
            $format = $this->container['distill.format_guesser']->guess($file);
        }

        return $this->container['distill.extractor.extractor']->extract($file, $path, $format);
    }

    /**
     * Extracts the compressed file and copies the files from the root directory
     * only if the compressed file contains a single directory.
     * @param string $file Compressed file.
     * @param string $path Destination path.
     * @param Format\FormatInterface $format Format.
     *
     * @throws NotSingleDirectoryException
     *
     * @return bool Returns TRUE when successful, FALSE otherwise
     */
    public function extractWithoutRootDirectory($file, $path, FormatInterface $format = null)
    {
        $filesystem = new Filesystem();

        // extract to a temporary place
        $tempDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid(time()) . DIRECTORY_SEPARATOR;
        $this->extract($file, $tempDirectory, $format);

        // move directory
        $iterator = new \FilesystemIterator($tempDirectory, \FilesystemIterator::SKIP_DOTS);

        $hasSingleRootDirectory = true;
        $singleRootDirectoryName = null;
        $numberDirectories = 0;

        while ($iterator->valid() && $hasSingleRootDirectory) {
            $uncompressedResource = $iterator->current();

            if (false === $uncompressedResource->isDir()) {
                $hasSingleRootDirectory = false;
            }

            $singleRootDirectoryName = $uncompressedResource->getRealPath();
            $numberDirectories++;

            if ($numberDirectories > 1) {
                $hasSingleRootDirectory = false;
            }

            $iterator->next();
        }

        if (false === $hasSingleRootDirectory) {
            // it is not a compressed file with a single directory
            $filesystem->remove($tempDirectory);

            throw new NotSingleDirectoryException($file);
        }

        $filesystem->remove($path);
        $filesystem->rename($singleRootDirectoryName, $path);

        return true;
    }

    /**
     * Checks if the file is the intended file to be decompressed and has not been manipulated.
     * @param string $file          File to be checked.
     * @param string $hashAlgorithm Hash algorithm.
     * @param string $expectedHash  Expected hash.
     * @param string $publicKey     Only when using signed hashes, public key.
     *
     * @throws HashAlgorithmNotSupportedException
     *
     * @return bool
     */
    public function isValidChecksum($file, $hashAlgorithm, $expectedHash, $publicKey = null)
    {
        // check if the hash has been signed
        if (null !== $publicKey) {
            $expectedHash = openssl_public_decrypt($expectedHash, $decrypted, $publicKey);
        }

        $hashAlgorithm = strtolower($hashAlgorithm);
        if (false === in_array($hashAlgorithm, hash_algos())) {
            throw new HashAlgorithmNotSupportedException($hashAlgorithm);
        }

        $hash = hash_file($hashAlgorithm, $file);

        return $hash === $expectedHash;
    }

    /**
     * Gets the file chooser.
     *
     * @return Chooser
     */
    public function getChooser()
    {
        return $this->container['distill.chooser'];
    }

}

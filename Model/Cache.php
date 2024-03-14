<?php

namespace Staempfli\ImageResizer\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;

class Cache
{
    protected Filesystem\Directory\WriteInterface $mediaDirectory;

    /**
     * @throws FileSystemException
     */
    public function __construct(
        Filesystem $filesystem
    ) {
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    /**
     * @throws FileSystemException
     */
    public function clearResizedImagesCache(): void
    {
        $this->mediaDirectory->delete(Resizer::IMAGE_RESIZER_CACHE_DIR);
    }
}

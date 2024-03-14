<?php

namespace Staempfli\ImageResizer\Model;

use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Image\AdapterFactory as ImageAdapterFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Resizer
{
    public const IMAGE_RESIZER_DIR = 'staempfli_imageresizer';
    public const IMAGE_RESIZER_CACHE_DIR = self::IMAGE_RESIZER_DIR . '/' . DirectoryList::CACHE;

    /**
     * - constrainOnly[true]: Guarantee, that image picture will not be bigger, than it was. It is false by default.
     * - keepAspectRatio[true]: Guarantee, that image picture width/height will not be distorted. It is true by default.
     * - keepTransparency[true]: Guarantee, that image will not lose transparency if any. It is true by default.
     * - keepFrame[false]: Guarantee, that image will have dimensions, set in $width/$height. Not applicable,
     * if keepAspectRatio(false).
     * - backgroundColor[null]: Default white
     */
    protected array $defaultSettings = [
        'constrainOnly' => true,
        'keepAspectRatio' => true,
        'keepTransparency' => true,
        'keepFrame' => false,
        'backgroundColor' => null,
        'quality' => 85
    ];

    protected array $subPathSettingsMapping = [
        'constrainOnly' => 'co',
        'keepAspectRatio' => 'ar',
        'keepTransparency' => 'tr',
        'keepFrame' => 'fr',
        'backgroundColor' => 'bc',
    ];

    protected array $resizeSettings = [];

    protected string $relativeFilename;

    protected int $width;

    protected int $height;

    protected Filesystem\Directory\WriteInterface|Filesystem\Directory\ReadInterface $mediaDirectoryRead;

    public function __construct(
        Filesystem $filesystem,
        protected ImageAdapterFactory $imageAdapterFactory,
        protected StoreManagerInterface $storeManager,
        protected File $fileIo,
        protected LoggerInterface $logger
    ) {
        $this->mediaDirectoryRead = $filesystem->getDirectoryRead(DirectoryList::MEDIA);
    }

    /**
     * Resized image and return url. Return original image url if no success
     */
    public function resizeAndGetUrl(string $imageUrl, ?int $width, ?int $height, array $resizeSettings = []): bool|string
    {
        try {
            // Set $resultUrl with $fileUrl to return this one in case the resize fails.
            $resultUrl = $imageUrl;
            $this->initRelativeFilenameFromUrl($imageUrl);
            if (!$this->relativeFilename) {
                return $resultUrl;
            }

            // Check if image is an animated gif return original gif instead of resized still.
            if ($this->isAnimatedGif($imageUrl)){
                return $resultUrl;
            }

            $this->initSize($width, $height);
            $this->initResizeSettings($resizeSettings);
        } catch (Exception $e) {
            $this->logger->addError("Staempfli_ImageResizer: could not find image: \n" . $e->getMessage());
        }
        try {
            // Check if resized image already exists in cache
            $resizedUrl = $this->getResizedImageUrl();
            if (!$resizedUrl) {
                if ($this->resizeAndSaveImage()) {
                    $resizedUrl = $this->getResizedImageUrl();
                }
            }
            if ($resizedUrl) {
                $resultUrl = $resizedUrl;
            }
        } catch (Exception $e) {
            $this->logger->addError("Staempfli_ImageResizer: could not resize image: \n" . $e->getMessage());
        }

        return $resultUrl;
    }

    /**
     * Prepare and set resize settings for image
     */
    protected function initResizeSettings(array $resizeSettings): void
    {
        // Init resize settings with default
        $this->resizeSettings = $this->defaultSettings;
        // Override resizeSettings only if key matches with existing settings
        foreach ($resizeSettings as $key => $value) {
            if (array_key_exists($key, $this->resizeSettings)) {
                $this->resizeSettings[$key] = $value;
            }
        }
    }

    /**
     * Init relative filename from original image url to resize
     */
    protected function initRelativeFilenameFromUrl(string $imageUrl): void
    {
        $this->relativeFilename = false; // reset filename in case there was another value defined
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $mediaPath = parse_url((string) $mediaUrl, PHP_URL_PATH);
        $imagePath = parse_url($imageUrl, PHP_URL_PATH);

        if (str_starts_with($imagePath, $mediaPath)) {
            $this->relativeFilename = substr_replace($imagePath, '', 0, strlen($mediaPath));
        }
    }

    protected function initSize(?int $width, ?int $height): void
    {
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Get sub folder name where the resized image will be saved
     *
     * In order to have unique folders depending on setting, we use the following logic:
     *      - <width>x<height>_[co]_[ar]_[tr]_[fr]_[quality]
     */
    protected function getResizeSubFolderName(): string
    {
        $subPath = $this->width . "x" . $this->height;
        foreach ($this->resizeSettings as $key => $value) {
            if ($value && isset($this->subPathSettingsMapping[$key])) {
                $subPath .= "_" . $this->subPathSettingsMapping[$key];
            }
        }

        return sprintf('%s_%s',$subPath, $this->resizeSettings['quality']);
    }

    /**
     * Get relative path where the resized image is saved
     * In order to have unique paths, we use the original image path plus the ResizeSubFolderName.
     */
    protected function getRelativePathResizedImage(): string
    {
        $pathInfo = $this->fileIo->getPathInfo($this->relativeFilename);
        $relativePathParts = [
            self::IMAGE_RESIZER_CACHE_DIR,
            $pathInfo['dirname'],
            $this->getResizeSubFolderName(),
            $pathInfo['basename']
        ];

        return implode('/', $relativePathParts);
    }

    protected function getAbsolutePathOriginal(): string
    {
        return $this->mediaDirectoryRead->getAbsolutePath($this->relativeFilename);
    }

    protected function getAbsolutePathResized(): string
    {
        return $this->mediaDirectoryRead->getAbsolutePath($this->getRelativePathResizedImage());
    }

    protected function getResizedImageUrl(): bool|string
    {
        $relativePath = $this->getRelativePathResizedImage();
        if ($this->mediaDirectoryRead->isFile($relativePath)) {
            return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . $relativePath;
        }
        return false;
    }

    /**
     * @throws Exception
     */
    protected function resizeAndSaveImage(): bool
    {
        if (!$this->mediaDirectoryRead->isFile($this->relativeFilename)) {
            return false;
        }

        $imageAdapter = $this->imageAdapterFactory->create();
        $imageAdapter->open($this->getAbsolutePathOriginal());
        if ($this->resizeSettings['watermark'] && file_exists($this->resizeSettings['watermark']['imagePath'])) {
            $imageAdapter->watermark(
                $this->resizeSettings['watermark']['imagePath'],
                $this->resizeSettings['watermark']['x'] ?? null,
                $this->resizeSettings['watermark']['y'] ?? null,
                $this->resizeSettings['watermark']['opacity'] ?? null,
                $this->resizeSettings['watermark']['tile'] ?? null
            );
        }
        $imageAdapter->constrainOnly($this->resizeSettings['constrainOnly']);
        $imageAdapter->keepAspectRatio($this->resizeSettings['keepAspectRatio']);
        $imageAdapter->keepTransparency($this->resizeSettings['keepTransparency']);
        $imageAdapter->keepFrame($this->resizeSettings['keepFrame']);
        $imageAdapter->backgroundColor($this->resizeSettings['backgroundColor']);
        $imageAdapter->quality($this->resizeSettings['quality']);
        $imageAdapter->resize($this->width, $this->height);
        $imageAdapter->save($this->getAbsolutePathResized());

        return true;
    }

    /**
     * Detects animated GIF from given file pointer resource or filename.
     */
    protected function isAnimatedGif(string $file): bool
    {
        if (is_string($file)) {
            if (!str_contains(strtolower($file), '.gif')) {
                return false;
            }
            $filePointer = fopen($file, "rb");
        } else {
            $filePointer = $file;
            /* Make sure that we are at the beginning of the file */
            fseek($filePointer, 0);
        }

        if (fread($filePointer, 3) !== "GIF") {
            fclose($filePointer);

            return false;
        }

        $frames = 0;

        while (!feof($filePointer) && $frames < 2) {
            if (fread($filePointer, 1) === "\x00") {
                /* Some of the animated GIFs do not contain graphic control extension (starts with 21 f9) */
                if (fread($filePointer, 1) === "\x21" || fread($filePointer, 2) === "\x21\xf9") {
                    $frames++;
                }
            }
        }

        fclose($filePointer);

        return $frames > 1;
    }
}

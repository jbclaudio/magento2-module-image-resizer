<?php

namespace Staempfli\ImageResizer\Block\Cache;

use Magento\Backend\Block\Cache\Additional as MagentoCacheAdditional;

class Additional extends MagentoCacheAdditional
{
    public function getCleanResizedImagesUrl(): string
    {
        return $this->getUrl('staempfli_imageresizer/cache/cleanResizedImages');
    }
}

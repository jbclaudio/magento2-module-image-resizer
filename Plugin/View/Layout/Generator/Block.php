<?php

namespace Staempfli\ImageResizer\Plugin\View\Layout\Generator;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Layout\Generator\Block as MagentoGeneratorBock;
use Staempfli\ImageResizer\Model\Resizer;

class Block
{
    public function __construct(
        protected Resizer $resizer
    ) {
    }

    /**
     * Add image resizer object to all template blocks
     */
    public function afterCreateBlock(MagentoGeneratorBock $subject, AbstractBlock $result): AbstractBlock //@codingStandardsIgnoreLine
    {
        if (is_a($result, Template::class)) {
            $result->addData(['image_resizer' => $this->resizer]);
        }

        return $result;
    }
}

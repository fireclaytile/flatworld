<?php
/**
 * Flatworld plugin for Craft CMS 3.x
 *
 * Craft Commerce plugin to provide Postie with an additional shipping provider.
 *
 * @link      https://github.com/fireclaytile
 * @copyright Copyright (c) 2023 Fireclay Tile
 */

namespace fireclaytile\flatworld\assetbundles\flatworld;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Fireclay Tile
 * @package   fireclaytile\flatworld\assetbundles\flatworld
 */
class FlatworldAsset extends AssetBundle {
    public function init() {
        $this->sourcePath = '@fireclaytile/flatworld/assetbundles/flatworld/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/Flatworld.js',
        ];

        $this->css = [
            'css/Flatworld.css',
        ];

        parent::init();
    }
}

<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use Grav\Common\Page\Header;
use Grav\Common\File\CompiledYamlFile;
use Grav\Plugin\LeafletTourPlugin;
use RocketTheme\Toolbox\File\MarkdownFile;

class PluginTest extends Test {

    protected function setup() {
        $pages = Grav::instance()['locator']->findResource('page://');
        $this->setFolderStructure($pages);
        $this->setPluginConfig();
        $this->setDatasets();
        $this->setTours($pages);
        $this->setViews($pages);
    }

    protected function setFolderStructure($pages) {
        $file = MarkdownFile::instance($pages.'/01.home/default.md');
        if (!$file->exists()) {
            $file->header(['title'=>'Home']);
            $file->save();
        }
        $file = MarkdownFile::instance($pages.'/01.home/01.subpage-1/default.md');
        if (!$file->exists()) {
            $file->header(['title'=>'Subpage 1']);
            $file->save();
        }
        $file = MarkdownFile::instance($pages.'/01.home/subpage-2/default.md');
        if (!$file->exists()) {
            $file->header(['title'=>'Subpage 2', 'routable'=>0]);
            $file->save();
        }
        $file = MarkdownFile::instance($pages.'/01.home/subpage-2/01.subpage-2-1/default.md');
        if (!$file->exists()) {
            $file->header(['title'=>'Subpage 2.1']);
            $file->save();
        }
        $file = MarkdownFile::instance($pages.'/01.home/subpage-2/02.subpage-2-2/default.md');
        if (!$file->exists()) {
            $file->header(['title'=>'Subpage 2.2']);
            $file->save();
        }
        $file = MarkdownFile::instance($pages.'/test-folder/default.md');
        if (!$file->exists()) {
            $file->header(['title'=>'Test Folder', 'routable'=>0]);
            $file->save();
        }
        $file = MarkdownFile::instance($pages.'/test-folder/01.test-subpage-1/default.md');
        if (!$file->exists()) {
            $file->header(['title'=>'Test Subpage 1']);
            $file->save();
        }
        $file = MarkdownFile::instance($pages.'/test-folder/test-subpage-2/default.md');
        if (!$file->exists()) {
            $file->header(['title'=>'Test Subpage 2']);
            $file->save();
        }
        $file = MarkdownFile::instance($pages.'/test-folder/test-subpage-2/test-subpage-2-1/default.md');
        if (!$file->exists()) {
            $file->header(['title'=>'Test Subpage 2.1']);
            $file->save();
        }
    }
    protected function setPluginConfig() {
        $configFile = CompiledYamlFile::instance(Grav::instance()['locator']->findResource('user://').'/config/plugins/leaflet-tour.yaml');
        $pluginData = array_merge($configFile->content(), [
            'basemaps'=>[
                [
                    'file'=>'Map1873.png',
                    'bounds'=>['south'=>27.474, 'west'=>-83.47, 'north'=>30.94, 'east'=>-80.35],
                    'zoom_max'=>11, // 'zoom_min'=>8,
                    'attribution_text'=>'Map 1873', 'attribution_url'=>null,
                ],[
                    'file'=>'Glot18.jpg',
                    'bounds'=>['south'=>28.873378634, 'west'=>-81.367955392, 'north'=>28.972581275, 'east'=>-81.262076589],
                    // 'zoom_max'=>16, 'zoom_min'=>8,
                ],[
                    'file'=>'Small Map.jpg',
                    'bounds'=>['south'=>28.873, 'west'=>-81.368, 'north'=>28.973, 'east'=>-81.262],
                    // 'zoom_max'=>16,
                    'zoom_min'=>13,
                    'attribution_text'=>'Small Map', 'attribution_url'=>'libraries.ou.edu',
                ],[
                    'file'=>'LakeMonroe.jpg',
                    'bounds'=>['south'=>27, 'west'=>-83, 'north'=>30, 'east'=>-80],
                    'zoom_max'=>15, 'zoom_min'=>10,
                    'attribution_text'=>'Lake Monroe', 'attribution_url'=>'https://libraries.ou.edu',
                ],[
                    'file'=>'VoCo.jpeg',
                    'bounds'=>['south'=>28, 'west'=>-81, 'north'=>28.3, 'east'=>-80.8],
                    'zoom_max'=>8, // 'zoom_min'=>8,
                    'attribution_text'=>null, 'attribution_url'=>null,
                ],
            ],
            'tile_server_select'=>'none',
            'attribution_html'=>[['test'=>'stuff']],
        ]);
        $configFile->content($pluginData);
        $configFile->save();
    }
    protected function setDatasets() {
        $dataset = Dataset::getDatasets()['points1.json'];
        $dataset->updateDataset(new Header([
            'legend_text'=>null,
            'icon'=>['file'=>'Wymansites.png'], // file set
            'features'=>[
                ['id'=>'points1_0', 'popup_content'=>'words'],
                ['id'=>'points1_1', 'popup_content'=>null],
                ['id'=>'points1_2', 'popup_content'=>'fu'],
                ['id'=>'points1_3', 'popup_content'=>'bar'],
            ],
        ]));
        $dataset = Dataset::getDatasets()['points3.json'];
        $dataset->updateDataset(new Header([
            'legend_text'=>'something',
            'legend_alt'=>'something else',
            'icon_alt'=>'Points 3 Icon Alt Text',
            'icon'=>[
                'file'=>null,
                'anchor_y'=>7, 'anchor_x'=>null,
                'tooltip_anchor_x'=>-5, 'tooltip_anchor_y'=>null,
                'shadow_width'=>10, 'shadow_height'=>12,
                'class'=>'icon-test-class',
            ],
            'features'=>[
                ['id'=>'points3_0', 'popup_content'=>null],
                ['id'=>'points3_1', 'popup_content'=>'popup'],
            ],
        ]));
        $dataset = Dataset::getDatasets()['polygons.json'];
        $dataset->updateDataset(new Header([
            'svg'=>[
                'color'=>'#445566',
                'weight'=>3,
                'fill'=>false,
                'fillColor'=>null,
            ],
            'svg_active'=>[
                'stroke'=>true,
                'fill'=>false,
                'fillOpacity'=>null,
            ],
        ]));
        // multiLineStrings.json - no setup needed, all default
    }
    protected function setTours($pages) {
        $defaults = [
            'visible'=>false,
            'content'=>['items'=>'@self.modular'],
            'start'=>['location'=>'none'],
            'legend'=>true,
            'legend_toggles'=>false,
            'only_show_view_features'=>false,
            'list_popup_buttons'=>true,
            'remove_tile_server'=>true,
        ];
        $file = MarkdownFile::instance($pages.'/tour-0/tour.md');
        $tour = array_merge($defaults, $file->header() ?? [], [
            'title'=>'Tour 0',
            'datasets'=>[
                ['file'=>'polygons.json', 'show_all'=>false],
                ['file'=>'points1.json', 'show_all'=>true],
                ['file'=>'points3.json', 'show_all'=>false],
                ['file'=>'multiPolygons.json', 'show_all'=>true],
                // no others, especially not points2.json
            ],
            'basemaps'=>[
                ['file'=>'Map1873.png'],
                ['file'=>'Small Map.jpg'],
            ],
            'start'=>[ // all options set and valid
                'bounds'=>['north'=>20, 'south'=>-20, 'east'=>130.5, 'west'=>78.43],
                'location'=>'points1_3',
                'lat'=>25, 'long'=>35,
                'distance'=>10,
            ],
            'maxBounds'=>['north'=>80, 'east'=>170, 'south'=>0, 'west'=>-170], // valid
            'remove_tile_server'=>true, // default
            'wide_column'=>true,
            'show_map_location_in_url'=>false,
            'zoom_max'=>15, // 'zoom_min'=>8
            'tile_server_select'=>'none',
            'tile_server'=>['url'=>null, 'attribution_text'=>'placeholder'],
            'attribution_list'=>[['text'=>'Map 1873', 'url'=>'google.com']], // overwrite basemap attribution
            'attribution_html'=>[['text'=>'<div>extra attribution with <a href="webaim.com">sample</a> link</div>']],
            'features'=>[
                ['id'=>'points3_0', 'popup_content'=>'something', 'remove_popup'=>true],
                ['id'=>'points1_0', 'remove_popup'=>true],
                ['id'=>'points2_0', 'popup_content'=>'this feature is not in any of the tour datasets', 'remove_popup'=>true],
                // exclude: points1_1, points1_2, points3_1, all polygons
            ]
        ]);
        $file->header($tour);
        $file->save();

        $file = MarkdownFile::instance($pages.'/tour-1/tour.md');
        $tour = array_merge($defaults, $file->header() ?? [], [
            'title'=>'Tour 1',
            'datasets'=>[],
            'maxBounds'=>['north'=>85, 'south'=>-60, 'east'=>65, 'west'=>null], // invalid (only three values provided)
            'start'=>['lat'=>10, 'long'=>10, 'distance'=>null], // valid coords, but no distance
            'tile_server_select'=>'stamenWatercolor',
            'attribution_list'=>[
                ['text'=>'Attribution Item', 'url'=>null], // no url
                ['text'=>null, 'url'=>'myfakeurl.com'], // no text
                ['text'=>'item 1', 'url'=>'libraries.ou.edu'], // url and text
                ['text'=>'qgis2web', 'url'=>null], // overwrite config
                ['text'=>'QGIS', 'url'=>'fakeurl.com'], // overwrite config
            ],
        ]);
        $file->header($tour);
        $file->save();
    }
    protected function setViews($pages) {
        $file = MarkdownFile::instance($pages.'/tour-0/_view-0/view.md');
        $file->header([
            'title'=>'View 0',
            'body_classes'=>'modular',
            'basemaps'=>[
                ['file'=>'Small Map.jpg'],
                ['file'=>'Glot18.jpg'],
            ],
            'start'=>[
                'bounds'=>['north'=>80, 'south'=>-92, 'east'=>60, 'west'=>40], // invalid
                'location'=>'points3_1', // hidden feature
                'lat'=>65, 'long'=>130.4,
                'distance'=>-5
            ],
            'only_show_view_features'=>true,
            'remove_tile_server'=>false,
            'features'=>[], // none
        ]);
        $file->save();
        $file = MarkdownFile::instance($pages.'/tour-0/_view-1/view.md');
        $file->header([
            'title'=>'View 1',
            'body_classes'=>'modular',
            'basemaps'=>[], // none
            'start'=>[
                'location'=>'points2_0', // invalid feature
                'distance'=>10,
            ],
            'only_show_view_features'=>true,
            'remove_tile_server'=>null,
            'features'=>[], // todo
        ]);
        $file->save();
        $file = MarkdownFile::instance($pages.'/tour-0/_view-2/view.md');
        $file->header([
            'title'=>'View 2',
            'body_classes'=>'modular',
            'start'=>[ // valid coordinates, distance causing wraparound, east becomes -171.89
                'lat'=>45, 'long'=>179.11,
                'distance'=>9,
            ],
        ]);
        $file->save();
    }

    protected function testPlugin() {
        // getDatasetFiles
        $datasets = LeafletTourPlugin::getDatasetFiles();
        $this->assertSize($datasets, 12);
        $this->assertEmpty($datasets['test1.json']);
        // getBasemaps
        $basemaps = LeafletTourPlugin::getBasemaps();
        $this->assertSize($basemaps, 5);
        $this->assertNotEmpty($basemaps['VoCo.jpeg']);
        $this->assertEmpty($basemaps['Map1873_copy.png']);
        // getTileServers
        $servers = LeafletTourPlugin::getTileServers();
        $this->assertSize($servers, 4); // 3 tile servers + 'none' option
        $this->assertEquals($servers['stamenWatercolor'], 'Stamen Watercolor');
        $this->assertEquals(LeafletTourPlugin::getTileServers('stamenTerrain')['name'], 'terrain');
    }
}
?>
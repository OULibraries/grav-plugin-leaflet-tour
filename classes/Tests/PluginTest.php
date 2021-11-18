<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use Grav\Common\Page\Header;
use Grav\Common\File\CompiledYamlFile;
use Grav\Plugin\LeafletTourPlugin;
use RocketTheme\Toolbox\File\MarkdownFile;

class PluginTest extends Test {

    /**
     * So we don't have to do this manually.
     */
    protected function setup() {
        $pages = Grav::instance()['locator']->findResource('page://');
        $this->setFolderStructure($pages);
        $this->setPluginConfig();
        $this->setDatasets();
        $this->setTours($pages);
        $this->setViews($pages);
    }

    /**
     * Creates the following folder structure. Unless otherwise specified, all pages are assumed to be routable and default.
     * 
     * - 01.home
     *     - 01.subpage-1
     *     - subpage-2 (non-routable)
     *         - 01.subpage-2-1
     *         - 02.subpage-2-2
     * - test-folder (non-routable)
     *     - 01.test-subpage-1
     *     - test-subpage-2
     *         - test-subpage-2-1
     * - 02.folder-for-tests (non-routable)
     *     - 01.tests (test.md)
     */
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
            $file->header(['title'=>'Test Folder']);
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
    /**
     * Sets plugin config options to needed defaults:
     * - Basemap details (zoom, bounds, etc.)
     * - No tile server selected
     * - One item in attribution_html
     */
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
                    'bounds'=>['south'=>28.87338, 'west'=>-81.36796, 'north'=>28.97258, 'east'=>-81.26208],
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
            'attribution_html'=>[['text'=>'stuff']],
        ]);
        $configFile->content($pluginData);
        $configFile->save();
    }
    /**
     * Sets needed defaults for datasets. For information about requirements for the files themselves, see the testing documentation.
     */
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
            'features'=>[
                ['id'=>'polygons_3', 'popup_content'=>'exists'],
            ],
        ]));
        $dataset = Dataset::getDatasets()['Loci.json'];
        $dataset->updateDataset(new Header([
            'name_prop'=>'LOCUS'
        ]));
    }
    /**
     * Sets needed defaults for tours.
     */
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
                ['file'=>'points1.json', 'show_all'=>true], // 12 features
                ['file'=>'points3.json', 'show_all'=>false],
                ['file'=>'multiPolygons.json', 'show_all'=>true], // 4 features
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
            'max_bounds'=>['north'=>80, 'east'=>170, 'south'=>0, 'west'=>-170], // valid
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
            'max_bounds'=>['north'=>85, 'south'=>-60, 'east'=>65, 'west'=>null], // invalid (only three values provided)
            'start'=>['lat'=>10, 'long'=>10, 'distance'=>null], // valid coords, but no distance
            'tile_server_select'=>'stamenWatercolor',
            'attribution_list'=>[
                ['text'=>'Attribution Item', 'url'=>null], // no url
                ['text'=>null, 'url'=>'myfakeurl.com'], // no text
                ['text'=>'item 1', 'url'=>'libraries.ou.edu'], // url and text
                ['text'=>'qgis2web', 'url'=>null], // overwrite config
                ['text'=>'QGIS', 'url'=>'new-qgis-url.com'], // overwrite config
            ],
        ]);
        $file->header($tour);
        $file->save();

        // tour for manual testing
        $file = MarkdownFile::instance($pages.'/03.test-tour/tour.md');
        $tour = array_merge($file->header() ?? [], [
            'title'=>'Test Tour',
            'content'=>['items'=>'@self.modular'],
            'datasets'=>[
                [
                    'file'=>'Wymansites.json',
                    'show_all'=>false,
                    'legend_text'=>'Wyman Site',
                    'icon'=>['file'=>'Wymansites.png']
                ],
                [
                    'file'=>'Wymancamps.json',
                    'show_all'=>false,
                    'legend_text'=>'Wyman Camp - this legend description is kind of long for testing purposes',
                    'legend_alt'=>'camp',
                    'icon_alt'=>'yellow hexagon',
                    'icon'=>['file'=>'Wymancamps.png']
                ],
                [
                    'file'=>'Loci.json',
                    'show_all'=>true,
                    'legend_text'=>'Loci',
                    'svg'=>['color'=>'#13bf2a']
                ],
                [
                    'file'=>'water.json',
                    'show_all'=>true,
                    'legend_text'=>null
                ],
                [
                    'file'=>'lineStrings.json',
                    'show_all'=>false,
                    'legend_text'=>'Line String',
                    'svg'=>['color'=>'#bf54a1']
                ],
                [
                    'file'=>'multiLineStrings.json',
                    'show_all'=>false,
                    'svg'=>['color'=>'#171e38']
                ],
                [
                    'file'=>'polygons.json',
                    'show_all'=>false,
                    'svg'=>['color'=>'#445566', 'fill'=>1, 'fillOpacity'=>0],
                    'svg_active'=>['fill'=>1, 'fillOpacity'=>0.2]
                ],
                [
                    'file'=>'multiPolygons.json',
                    'show_all'=>false,
                    'svg'=>['color'=>'#30ad9a']
                ]
            ],
            'start'=>['location'=>'default'],
            'legend'=>true,
            'legend_toggles'=>true,
            'only_show_view_features'=>true,
            'list_popup_buttons'=>false,
            'remove_tile_server'=>true,
            'basemaps'=>[['file'=>'Map1873.png']],
            'features'=>[
                ['id'=>'Wymansites_0','remove_popup'=>false, 'popup_content'=>"This popup is testing adding an image from the tour page (feature overrides).\n\n![Monarch butterfly](image://monarch.jpg)\n\n### Lorem Ipsum Content\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Dolor purus non enim praesent elementum facilisis. Interdum velit laoreet id donec ultrices tincidunt arcu non sodales. A diam maecenas sed enim. Commodo sed egestas egestas fringilla phasellus faucibus scelerisque eleifend donec. At erat pellentesque adipiscing commodo elit. Quisque non tellus orci ac auctor augue. At varius vel pharetra vel turpis nunc. Aliquet eget sit amet tellus cras adipiscing enim eu. Arcu bibendum at varius vel. Dignissim enim sit amet venenatis urna cursus eget nunc scelerisque. Viverra suspendisse potenti nullam ac tortor vitae. Nulla posuere sollicitudin aliquam ultrices sagittis orci. Tortor at risus viverra adipiscing at in tellus integer feugiat. Amet mauris commodo quis imperdiet massa tincidunt nunc pulvinar sapien. Sit amet nisl purus in mollis nunc sed id. Velit euismod in pellentesque massa placerat duis ultricies lacus. Interdum varius sit amet mattis vulputate enim. Risus sed vulputate odio ut enim blandit. Lectus proin nibh nisl condimentum id.\n\nElementum facilisis leo vel fringilla. Nulla aliquet enim tortor at auctor urna nunc id cursus. Egestas sed sed risus pretium. In vitae turpis massa sed. Praesent elementum facilisis leo vel fringilla est. Malesuada bibendum arcu vitae elementum curabitur. Facilisi cras fermentum odio eu feugiat. Sit amet mauris commodo quis imperdiet massa tincidunt nunc pulvinar. Cursus eget nunc scelerisque viverra mauris in aliquam. Elementum curabitur vitae nunc sed velit dignissim. Amet justo donec enim diam vulputate ut pharetra sit. Purus non enim praesent elementum facilisis leo vel fringilla. Morbi enim nunc faucibus a pellentesque.\n\nPretium vulputate sapien nec sagittis aliquam malesuada bibendum. Augue lacus viverra vitae congue. Curabitur vitae nunc sed velit dignissim sodales ut eu sem. Tellus in metus vulputate eu scelerisque. Lobortis scelerisque fermentum dui faucibus in. Orci porta non pulvinar neque laoreet suspendisse interdum consectetur libero. Eget velit aliquet sagittis id consectetur. Sollicitudin tempor id eu nisl nunc mi ipsum faucibus. Sociis natoque penatibus et magnis dis parturient montes. Eu ultrices vitae auctor eu augue. Adipiscing vitae proin sagittis nisl rhoncus mattis rhoncus urna neque. Cras pulvinar mattis nunc sed. Maecenas sed enim ut sem viverra aliquet eget sit amet. Cursus in hac habitasse platea dictumst quisque. Sed blandit libero volutpat sed cras ornare arcu dui vivamus. Tortor condimentum lacinia quis vel eros donec ac. Cursus vitae congue mauris rhoncus.\n\nUrna nunc id cursus metus aliquam eleifend. Aliquam sem et tortor consequat id porta nibh venenatis cras. Nulla facilisi morbi tempus iaculis urna id volutpat lacus. Viverra nibh cras pulvinar mattis nunc sed blandit. In mollis nunc sed id. Faucibus turpis in eu mi bibendum neque egestas congue. Phasellus vestibulum lorem sed risus ultricies tristique nulla aliquet enim. Fusce id velit ut tortor pretium. Tincidunt vitae semper quis lectus nulla at volutpat. Placerat duis ultricies lacus sed. Libero nunc consequat interdum varius sit amet mattis.\n\nNam at lectus urna duis. Convallis convallis tellus id interdum velit laoreet id. Morbi blandit cursus risus at. Turpis tincidunt id aliquet risus. Id nibh tortor id aliquet lectus proin nibh nisl condimentum. Massa eget egestas purus viverra accumsan. Fames ac turpis egestas integer eget aliquet nibh. Sed arcu non odio euismod. Ornare arcu odio ut sem nulla pharetra diam sit. Sagittis id consectetur purus ut faucibus pulvinar elementum integer enim. Lorem sed risus ultricies tristique nulla. Neque viverra justo nec ultrices dui sapien eget. Est placerat in egestas erat."],
                ['id'=>'Wymansites_1','remove_popup'=>false],
                ['id'=>'Wymansites_2','remove_popup'=>false],
                ['id'=>'Wymansites_3','remove_popup'=>false],
                ['id'=>'Wymansites_5','remove_popup'=>false],
                ['id'=>'Wymansites_10','remove_popup'=>false],
                ['id'=>'Wymansites_13','remove_popup'=>false, 'popup_content'=>"Popup content for Live Oak"],
                ['id'=>'Wymansites_18','remove_popup'=>false],
                ['id'=>'Wymansites_19','remove_popup'=>false],
                ['id'=>'Wymansites_21','remove_popup'=>false],
                ['id'=>'Wymancamps_0','remove_popup'=>false],
                ['id'=>'Wymancamps_1','remove_popup'=>false],
                ['id'=>'Wymancamps_3','remove_popup'=>false],
                ['id'=>'Wymancamps_5','remove_popup'=>false],
                ['id'=>'Wymancamps_6','remove_popup'=>false],
                ['id'=>'Wymancamps_7','remove_popup'=>false],
                ['id'=>'Wymancamps_9','remove_popup'=>false],
                ['id'=>'Wymancamps_11','remove_popup'=>false],
                ['id'=>'Wymancamps_12','remove_popup'=>false],
                ['id'=>'Wymancamps_17','remove_popup'=>false],
                ['id'=>'lineStrings_0','remove_popup'=>false, 'popup_content'=>'This LineString has five points.'],
                ['id'=>'lineStrings_1','remove_popup'=>false],
                ['id'=>'multiLineStrings_0','remove_popup'=>false, 'popup_content'=>'This MultiLineString has a line of three points and a line of four points.'],
                ['id'=>'multiLineStrings_1','remove_popup'=>false],
                ['id'=>'polygons_0','remove_popup'=>false, 'popup_content'=>'This Polygon has two empty chunks.'],
                ['id'=>'polygons_1','remove_popup'=>false],
                ['id'=>'multiPolygons_0','remove_popup'=>false, 'popup_content'=>'This MultiPolygon has two polygons.'],
                ['id'=>'multiPolygons_1','remove_popup'=>false]
            ],
            'zoom_min'=>8,
            'zoom_max'=>16,
            'tile_server_select'=>'stamenWatercolor'
        ]);
        $file->header($tour);
        $file->save();
    }
    /**
     * Sets needed defaults for views.
     */
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
                'location'=>'points3_3', // hidden feature
                'lat'=>65, 'long'=>130.4,
                'distance'=>5
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
            'features'=>[ // 5 valid, 3 with popups
                ['id'=>'points3_0'], // valid - not show all, but in features list, has popup
                ['id'=>'points1_2'], // valid - show all, not in features list, has popup
                ['id'=>'multiPolygons_3'], // valid - show all, not in features list, no popup
                ['id'=>'points1_0'], // valid - in features list, popup removed
                ['id'=>'points1_3'], // valid - show all, has popup
                ['id'=>'polygons_3'], // invalid - not show all, not in features list, has popup
                ['id'=>'points2_0'], // invalid - not in dataset, but in features list, has popup
                ['id'=>'Wymancamps_0'], // invalid - not in tour dataset
            ],
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
            'features'=>[ // 3 valid features with popups (same from view 1)
                ['id'=>'points3_0'], // valid - not show all, but in features list, has popup
                ['id'=>'points1_2'], // valid - show all, not in features list, has popup
                ['id'=>'points1_3'], // valid - show all, has popup
            ],
        ]);
        $file->save();

        // views for manual testing
        $file = MarkdownFile::instance($pages.'/03.test-tour/_view-1/view.md');
        $view = [
            'title'=>'View 1',
            'body_classes'=>'modular',
            'start'=>['location'=>'default'],
            'features'=>[
                ['id'=>'water_0'], // 1
                ['id'=>'Loci_0'], // A
                ['id'=>'Loci_1'], // B
                ['id'=>'Loci_2'], // C
                ['id'=>'Loci_3'], // E
                ['id'=>'Loci_4'], // MR123
                ['id'=>'Loci_5'], // EAST
                ['id'=>'Loci_6'] // D
            ],
            'only_show_view_featurues'=>false,
            'list_popup_buttons'=>true
        ];
        $file->header(array_merge($file->header ?? [], $view));
        $file->save();
        $file = MarkdownFile::instance($pages.'/03.test-tour/_view-2/view.md');
        $view = [
            'title'=>'View 2',
            'body_classes'=>'modular',
            'start'=>['location'=>'default'],
            'features'=>[
                ['id'=>'Wymancamps_11'], // Ropes Island
                ['id'=>'Wymancamps_9'], // Orange Bluff
                ['id'=>'Wymansites_10'], // Lungren Island
                ['id'=>'Wymansites_1'], // Astor Midden
                ['id'=>'Wymansites_2'] // Bartram's Mound
            ],
        ];
        $file->header(array_merge($file->header ?? [], $view));
        $file->save();
        $file = MarkdownFile::instance($pages.'/03.test-tour/_view-3/view.md');
        $view = [
            'title'=>'View 3',
            'body_classes'=>'modular',
            'start'=>['location'=>'default'],
            'features'=>[
                ['id'=>'Wymansites_19'], // Watson's Landing
                ['id'=>'Wymansites_5'], // Palatka Midden
                ['id'=>'Wymancamps_3'] // Phillipstown
            ],
        ];
        $file->header(array_merge($file->header ?? [], $view));
        $file->save();
        $file = MarkdownFile::instance($pages.'/03.test-tour/_view-4/view.md');
        $view = [
            'title'=>'View 4',
            'body_classes'=>'modular',
            'start'=>['location'=>'default'],
            'features'=>[
                ['id'=>'lineStrings_0'],
                ['id'=>'lineStrings_1']
            ],
        ];
        $file->header(array_merge($file->header ?? [], $view));
        $file->save();
        $file = MarkdownFile::instance($pages.'/03.test-tour/_view-5/view.md');
        $view = [
            'title'=>'View 5',
            'body_classes'=>'modular',
            'start'=>['location'=>'default'],
            'features'=>[
                ['id'=>'multiLineStrings_0'],
                ['id'=>'multiLineStrings_1']
            ],
        ];
        $file->header(array_merge($file->header ?? [], $view));
        $file->save();
        $file = MarkdownFile::instance($pages.'/03.test-tour/_view-6/view.md');
        $view = [
            'title'=>'View 6',
            'body_classes'=>'modular',
            'start'=>['location'=>'default'],
            'features'=>[
                ['id'=>'polygons_0'],
                ['id'=>'polygons_1']
            ],
        ];
        $file->header(array_merge($file->header ?? [], $view));
        $file->save();
        $file = MarkdownFile::instance($pages.'/03.test-tour/_view-7/view.md');
        $view = [
            'title'=>'View 7',
            'body_classes'=>'modular',
            'start'=>['location'=>'default'],
            'features'=>[
                ['id'=>'multiPolygons_0'],
                ['id'=>'multiPolygons_1']
            ],
        ];
        $file->header(array_merge($file->header ?? [], $view));
        $file->save();
    }

    /**
     * Test functions in leaflet-tour.php
     * 
     * Requires:
     *  - uploads:
     *      - 12 valid datasets (test1.json uploaded but not valid)
     *      - 6 basemaps
     *  - settings:
     *      - 5 basemaps provided valid settings (includes VoCo.jpeg, does not include Map1873_copy.png)
     */
    protected function testPlugin() {
        // getDatasetFiles
        $datasets = LeafletTourPlugin::getDatasetFiles();
        // Make sure 12 uploaded files have been recognized as valid datasets.
        $this->assertSize($datasets, 12);
        // Make sure 'test1.json' is not recognized as a valid dataset.
        $this->assertEmpty($datasets['test1.json']);
        // getBasemaps
        $basemaps = LeafletTourPlugin::getBasemaps();
        $this->assertSize($basemaps, 5);
        $this->assertNotEmpty($basemaps['VoCo.jpeg']);
        $this->assertEmpty($basemaps['Map1873_copy.png']);
        // getTileServers
        $servers = LeafletTourPlugin::getTileServers();
        // Make sure that the three Stamen maps are being offered as options for tile servers, as well as a 'none' option.
        $this->assertSize($servers, 4);
        // Make sure the 'stamenWatercolor' and 'stamenTerrain' tile servers exist.
        $this->assertEquals($servers['stamenWatercolor'], 'Stamen Watercolor');
        $this->assertEquals(LeafletTourPlugin::getTileServers('stamenTerrain')['name'], 'terrain');
    }

    /**
     * Test getTourFeatures method from leaflet-tour.php
     * 
     * Requires:
     *  - tour 0 with datasets:
     *      - polygons.json - 4 features (0 in list)
     *      - points1.json - 12 features (show all)
     *      - points3.json - 5 features (1 in list)
     *      - multiPolygons.json - 4 features (show all)
     *      - 1 feature from another dataset (that exists) in list ()
     */
    protected function testGetTourFeatures() {
        // tour gets appropriate number of features
        $tour0route = Utils::getPageRoute(['tour-0']).'tour.md';
        $this->assertSize(LeafletTourPlugin::getTourFeatures(false, false, $tour0route), 25);
        // tour gets appropriate number of point features (12 + 5 + 1 (for none))
        $this->assertSize(LeafletTourPlugin::getTourFeatures(true, false, $tour0route), 18);
        // view gets appropriate number of features (12 + 1 + 4)
        $this->assertSize(LeafletTourPlugin::getTourFeatures(false, true, $tour0route), 17);
        // view gets appropriate number of point features (should be same as tour - the point features does not have to be shown in the tour, just part of one of the datasets)
        $this->assertSize(LeafletTourPlugin::getTourFeatures(true, true, $tour0route), 18);
        // ensure that one of the features that should exist in the tour features list does exist
        $list = LeafletTourPlugin::getTourFeatures(false, true, $tour0route);
        $this->assertNotEmpty($list['multiPolygons_0']);
        // a tour feature that exists, but is not from a tour dataset, is not included in view features list
        $this->assertEmpty($list['points2_0']);
        // add dataset that does not exist
        $file = MarkdownFile::instance($tour0route);
        $tour0header = $file->header();
        $datasets = array_merge($tour0header['datasets'], [['file'=>'doesnotexist.json', 'show_all'=>true]]);
        $file->header(array_merge($tour0header, ['datasets'=>$datasets]));
        $file->save();
        // make sure dataset was added
        $this->assertSize($file->header()['datasets'], 5);
        // calling tour features when a tour dataset does not exist does not cause error
        $this->assertNotEmpty(LeafletTourPlugin::getTourFeatures(false, false, $tour0route));
        // calling tour features for view when a tour dataset does not exist (show all) does not cause error
        $this->assertNotEmpty(LeafletTourPlugin::getTourFeatures(false, true, $tour0route));
        // remove non-existent dataset, and add non-existent feature
        $features = array_merge($tour0header['features'], [['id'=>'doesnotexist_0', 'remove_popup'=>false]]);
        $file->header(array_merge($tour0header, ['features'=>$features]));
        $file->save();
        $this->assertSize($file->header()['features'], 4);
        // calling tour features for view when a tour feature does not exist does not cause error, and the non-existent feature is not in the view features list (list should be same size as it was when checked previously)
        $this->assertSize(LeafletTourPlugin::getTourFeatures(false, true, $tour0route), 17);
        // revert tour header to previous
        $file->header($tour0header);
        $file->save();
    }
}
?>
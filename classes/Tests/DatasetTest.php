<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Data\Data;
use Grav\Common\Page\Header;

class DatasetTest extends Test {

    /**
     * Test the updateDataset method.
     * 
     * Requires lineStrings.json dataset with the following:
     * - title: 'LineStrings Dataset'
     * - name_prop: name
     * - 6 valid features
     *     - lineStrings_2: Does not have name or featureName properties.
     *     - lineStrings_3: Does not have name property.
     */
    protected function testUpdateDataset() {
        $lineStrings = Dataset::getDatasets()['lineStrings.json'];
        $lineStringsYaml = $lineStrings->asYaml();
        $update = [
            'title'=>'',
            'name_prop'=>'This property does not exist',
            'features'=>[
                ['id'=>'lineStrings_0', 'custom_name'=>'LineStrings 0 Update'], // add custom name
                // no lineStrings_1 - trying to remove a property
                ['id'=>'lineStrings_2', 'properties'=>['name'=>'LineStrings 2 Update']], // add name property to feature that did not have it
                ['id'=>'lineStrings_3', 'properties'=>['featureName'=>'LineStrings 3 Update']], // will be name property in a future update
                ['id'=>'lineStrings_4', 'properties'=>['name'=>'LineStrings 4 Update - name', 'featureName'=>'LineStrings 4 Update - featureName']], // current and future name properties
                ['id'=>'lineStrings_20', 'custom_name'=>'LineStrings 20', 'coordinates'=>[[0,0], [1,1], [2,2]]], // trying to add a feature
            ],
        ];
        $header = $lineStrings->updateDataset(new Header($update));
        $features = $lineStrings->getFeatures();
        // not possible to set title to empty
        $this->assertEquals($header->get('title'), 'LineStrings Dataset');
        $this->assertEquals($lineStrings->getName(), 'LineStrings Dataset');
        // not possible to change name property to a property that does not exist
        $this->assertEquals($header->get('name_prop'), 'name');
        // not possible to add or remove features: count is 6 if no change, 4 if two features are removed, 7 if one feature is added, and 5 if features are added and removed
        $this->assertSize($features, 6);
        // update feature custom name and feature property
        $this->assertEquals($features['lineStrings_0']->getName(), 'LineStrings 0 Update');
        $this->assertEquals($features['lineStrings_3']->getProperties()['featureName'], 'LineStrings 3 Update');
        // add name property to a feature without it
        $this->assertEquals($features['lineStrings_2']->getName(), 'LineStrings 2 Update');
        // change name property
        $lineStrings->updateDataset(new Header(['name_prop'=>'featureName']));
        $features = $lineStrings->getFeatures();
        $this->assertEquals($lineStrings->getNameProperty(), 'featureName');
        // feature with custom name keeps its name
        $this->assertEquals($features['lineStrings_0']->getName(), 'LineStrings 0 Update');
        // feature with the previous name property but not the current loses its name
        $this->assertEquals($features['lineStrings_2']->getName(), 'lineStrings_2');
        $this->assertEmpty($features['lineStrings_2']->getProperties()['featureName']);
        // feature without the previous name property but with the current changes its name
        $this->assertEquals($features['lineStrings_3']->getName(), 'LineStrings 3 Update');
        // feature with both the previous and current name properties changes its name
        $this->assertEquals($features['lineStrings_4']->getName(), 'LineStrings 4 Update - featureName');
        // reset dataset
        $lineStrings->updateDataset(new Header($lineStringsYaml));
    }

    /**
     * Test possibility of reordering features in a dataset.
     */
    protected function testUpdateDataset_order() {
        $points = Dataset::getDatasets()['points1.json'];
        $pointsYaml = $points->asYaml(); // save current dataset settings
        // reordering features
        $update = [
            'features'=>[
                ['id'=>'points1_4'], ['id'=>'points1_0'], ['id'=>'points1_11'],
                ['id'=>'points1_9'], ['id'=>'points1_5'], ['id'=>'points1_2'],
                ['id'=>'points1_1'], ['id'=>'points1_6'], ['id'=>'points1_10'],
                // 3, 7, 8
            ]
        ];
        $points->updateDataset(new Header($update));
        $features = array_keys($points->getFeatures());
        $this->assertEquals($features, ['points1_4', 'points1_0', 'points1_11', 'points1_9', 'points1_5', 'points1_2', 'points1_1', 'points1_6', 'points1_10', 'points1_3', 'points1_7', 'points1_8']);
        // revert to previous dataset settings
        $points->updateDataset(new Header($pointsYaml));
        $this->assertFalse(array_keys($points->getFeatures()) === ['points1_4', 'points1_0', 'points1_11', 'points1_9', 'points1_5', 'points1_2', 'points1_1', 'points1_6', 'points1_10', 'points1_3', 'points1_7', 'points1_8']);
    }

    /**
     * Ensure that defaults (for svg and svg_active) are set correctly when a new dataset is created. We cannot rely on specifying defauls in the blueprints, because the dataset configuration is not initially saved from the admin panel.
     * 
     * Requires unmodified settings for:
     * - points3.json: svg.color
     * - multiLineStrings: svg.fill, svg_active.opacity, svg_active.weight
     */
    protected function testSetDefaults() {
        $this->assertEquals(Dataset::getDatasets()['points3.json']->asYaml()['svg']['color'], '#3388ff');
        $multiLineStrings = Dataset::getDatasets()['multiLineStrings.json']->asYaml();
        $this->assertTrue($multiLineStrings['svg']['fill']);
        $this->assertEmpty($multiLineStrings['svg_active']['opacity']);
        $this->assertEquals($multiLineStrings['svg_active']['weight'], 5);
    }

    /**
     * Test the mergeTourData method.
     * 
     * Requires:
     *  - datasets: points1, points3, polygons
     *  - points3:
     *      - legend alt text set
     *      - icon alt text: Points 3 Icon Alt Text
     * 
     */
    protected function testMergeTourData() {
        // point dataset gets iconOptions array, but no pathOptions
        $pointsData = Dataset::getDatasets()['points1.json']->mergeTourData(new Data(['show_all'=>true, 'legend_text'=>'', 'icon'=>['width'=>5]]), []);
        $this->assertNotEmpty($pointsData->get('iconOptions'));
        $this->assertEmpty($pointsData->get('pathOptions'));
        // non-point dataset gets pathOptions and pathActiveOptions, but no iconOptions
        $polyData = Dataset::getDatasets()['polygons.json']->mergeTourData(new Data(['show_all'=>false, 'legend_text'=>'unimportant', 'svg'=>['fill'=>true]]), []);
        $this->assertNotEmpty($polyData->get('pathOptions'));
        $this->assertEmpty($polyData->get('iconOptions'));
        // tour dataset set with show_all=false, no features in tour features list, and legend text set does not get a legend
        $this->assertEmpty($polyData->get('legend'));
        // tour dataset with features but no legend text does not get a legend
        $this->assertEmpty($pointsData->get('legend'));
        // legend text set by tour overrides legend alt text with empty string and legend alt text set by dataset
        $pointsData = Dataset::getDatasets()['points3.json']->mergeTourData(new Data(['show_all'=>false, 'legend_alt'=>'', 'legend_text'=>'Points 3 Tour Legend']), [['id'=>'points3_0']]);
        $this->assertEquals($pointsData->get('legend.legendText'), 'Points 3 Tour Legend');
        // dataset icon alt text
        $this->assertEquals($pointsData->get('legend.iconAltText'), 'Points 3 Icon Alt Text');
        // dataset icon alt text is not used if tour is providing the icon url
        $pointsData = Dataset::getDatasets()['points3.json']->mergeTourData(new Data(['show_all'=>true, 'legend_text'=>'fu', 'icon'=>['file'=>'Wymancamps.png']]), []);
        $this->assertEmpty($pointsData->get('legend.iconAltText'));
        // dataset icon alt text is not used if use_defaults is true
        $pointsData = Dataset::getDatasets()['points3.json']->mergeTourData(new Data(['show_all'=>true, 'legend_text'=>'fu', 'icon'=>['use_defaults'=>true]]), []);
        $this->assertEmpty($pointsData->get('legend.iconAltText'));
        // legend path fillColor is not included if fill is false
        $polyData = Dataset::getDatasets()['polygons.json']->mergeTourData(new Data(['show_all'=>true, 'legend_text'=>'unimportant', 'svg'=>['fill'=>false, 'fillColor'=>'#334455']]), []);
        $this->assertEmpty($polyData->get('legend.fillColor'));
        // legend path color is not included if stroke is false
        $polyData = Dataset::getDatasets()['polygons.json']->mergeTourData(new Data(['show_all'=>true, 'legend_text'=>'unimportant', 'svg'=>['stroke'=>false, 'color'=>'#334455']]), []);
        $this->assertEmpty($polyData->get('legend.color'));
    }

    /**
     * Test the mergeIconOptions method (method is protected, but called by mergeTourData)
     * 
     * Requires:
     *  - datasets: points1, points3,
     *  - points1
     *      - icon file: set
     *      - anchor: not set
     *  - points3
     *      - icon file: not set
     *      - anchor: [x: null/not set, y: 7]
     *      - tooltip anchor: (x: -5, y: null/not set)
     *      - shadow url: not set
     *      - shadow size: (width: 10, height: anything other than 3)
     *      - class: 'icon-test-class'
     * 
     */
    protected function testMergeIconOptions() {
        // use_defaults=true, icon file not set in tour, file set in dataset
        $data = Dataset::getDatasets()['points1.json']->mergeTourData(new Data(['icon'=>['use_defaults'=>true, 'anchor_x'=>25], 'shadow_anchor_y'=>-12]), []);
        // file provided by dataset - icon file is generic
        $this->assertEquals($data->get('iconOptions.iconUrl'), Utils::DEFAULT_MARKER_OPTIONS['iconUrl']);
        // default width
        $this->assertEquals($data->get('iconOptions.iconSize.0'), Utils::DEFAULT_MARKER_OPTIONS['iconSize'][0]);
        // anchor with only x
        $this->assertEquals($data->get('iconOptions.iconAnchor.0'), 25);
        // shadow anchor with only y (and shadow file not specifically set) - does not work
        $this->assertEmpty($data->get('iconOptions.shadowAnchor.1'));

        // use_defaults=true, icon file set in tour
        $data = Dataset::getDatasets()['points1.json']->mergeTourData(new Data(['icon'=>['use_defaults'=>true, 'file'=>'Wymancamps.png', 'width'=>18]]), []);
        // default height
        $this->assertEquals($data->get('iconOptions.iconSize.1'), Utils::MARKER_FALLBACKS['iconSize'][1]);
        // icon width set by tour
        $this->assertEquals($data->get('iconOptions.iconSize.0'), 18);

        // use_defaults=false, icon file not set in tour or dataset
        $data = Dataset::getDatasets()['points3.json']->mergeTourData(new Data(['icon'=>['use_defaults'=>false, 'shadow_height'=>3]]), []);
        // tooltip anchor set in dataset
        $this->assertEquals($data->get('iconOptions.tooltipAnchor.0'), -5);
        // shadow width set in dataset
        $this->assertEquals($data->get('iconOptions.shadowSize.0'), 10);
        // shadow height set in dataset and tour
        $this->assertEquals($data->get('iconOptions.shadowSize.1'), 3);
        // class set in dataset is appropriately added to default class
        $this->assertEquals($data->get('iconOptions.className'), 'leaflet-marker icon-test-class');

        // use_defaults=false, icon file set in dataset or tour
        // anchor with only x - doesn't work
        $data = Dataset::getDatasets()['points1.json']->mergeTourData(new Data(['icon'=>['use_defaults'=>false, 'anchor_x'=>12]]), []);
        $this->assertEmpty($data->get('iconOptions.iconAnchor'));
        // anchor with only y - doesn't work
        $data = Dataset::getDatasets()['points1.json']->mergeTourData(new Data(['icon'=>['use_defaults'=>false, 'anchor_y'=>12]]), []);
        $this->assertEmpty($data->get('iconOptions.iconAnchor'));
        // anchor with x in tour and y in dataset - works
        $data = Dataset::getDatasets()['points3.json']->mergeTourData(new Data(['icon'=>['use_defaults'=>false, 'file'=>'Wymancamps.png', 'anchor_x'=>12]]), []);
        $this->assertNotEmpty($data->get('iconOptions.iconAnchor'));
        // shadow height and width are set, no shadow url - empty shadowSize
        $this->assertEmpty($data->get('iconOptions.shadowSize'));
    }

    /**
     * Test the mergePathOptions method (method is protected, but called by mergeTourData)
     * 
     * Requires:
     *  - dataset: polygons
     *  - polygons
     *      - svg.color: #445566
     *      - svg.weight: anything but 2
     *      - svg.fill: false
     *      - svg.fillColor not set
     *      - svg_active.stroke: true
     *      - svg_active.fill: false
     *      - svg_active.fillOpacity: null
     */
    protected function testMergePathOptions() {
        $data = Dataset::getDatasets()['polygons.json']->mergeTourData(new Data(['svg'=>['weight'=>2], 'svg_active'=>['stroke'=>false, 'fill'=>true, 'fillOpacity'=>0.5]]), []);
        // fillColor - not set in tour or dataset
        $this->assertEmpty($data->get('pathOptions.fillColor'));
        // active fill - true in tour, false in dataset
        $this->assertTrue($data->get('pathActiveOptions.fill'));
        // active stroke - false in tour, true in dataset
        $this->assertEmpty($data->get('pathActiveOptions.stroke'));
        // fill - null in tour, false in dataset
        $this->assertEmpty($data->get('pathOptions.fill'));
        // color - null in tour, '#445566' in dataset
        $this->assertEquals($data->get('pathOptions.color'), '#445566');
        // active fillOpacity - 0.5 in tour, null in dataset
        $this->assertEquals($data->get('pathActiveOptions.fillOpacity'), 0.5);
        // weight - 2 in tour, 3 in dataset
        $this->assertEquals($data->get('pathOptions.weight'), 2);
    }

    /**
     * Test the mergeFeatures method (method is protected, but called by mergeTourData)
     * 
     * Requires:
     *  - dataset: points1
     *  - points1:
     *      - 12 features
     *      - points1_3 name: Point 3
     *      - points1_0 and points1_2 with popup content
     *      - points1_1: no popup content
     */
    protected function testMergeFeatures() {
        // show_all = false
        $data = Dataset::getDatasets()['points1.json']->mergeTourData(new Data(['show_all'=>false]), [
            ['id'=>'points1_0', 'remove_popup'=>true], 
            ['id'=>'points1_1', 'popup_content'=>'set by tour'], 
            ['id'=>'points1_2', 'popup_content'=>'overwritten by tour', 'remove_popup'=>true], 
            ['id'=>'points1_3']]);
        // correct number of features (12 total, only 4 in list)
        $this->assertSize($data->get('features'), 4);
        // correct number of hidden features
        $this->assertSize($data->get('hiddenFeatures'), 8);
        // feature included in tour features list
        $this->assertEquals($data->get('features.points1_3.name'), 'Point 3');
        // feature in dataset, not in tour features list
        $this->assertEmpty($data->get('features.points1_5'));
        $this->assertNotEmpty($data->get('hiddenFeatures.points1_5'));
        // feature with popup content in dataset, but remove_popup
        $this->assertEmpty($data->get('features.points1_0.popupContent'));
        // feature with popup content in tour and dataset, but remove_popup
        $this->assertEquals($data->get('features.points1_2.popupContent'), 'overwritten by tour');
        // feature with popup content set by tour
        $this->assertEquals($data->get('features.points1_1.popupContent'), 'set by tour');

        // show_all=true
        $data = Dataset::getDatasets()['points1.json']->mergeTourData(new Data(['show_all'=>true]), []);
        // no hidden features
        $this->assertSize($data->get('features'), 12);
        $this->assertSize($data->get('hiddenFeatures'), 0);
        // feature not in features list
        $this->assertEquals($data->get('features.points1_3.name'), 'Point 3');
    }

    /**
     * Test the static getDatasetList function
     * 
     * Requires: lineStrings dataset with name "LineStrings Dataset"
     */
    protected function testGetDatasetList() {
        // check for correct dataset name
        $this->assertEquals(Dataset::getDatasetList()['lineStrings.json'], 'LineStrings Dataset');
    }

    /**
     * Test the static getDatasets method
     * 
     * Requires:
     *  - 12 datasets
     *  - points1 dataset with 12 features
     */
    protected function testGetDatasets() {
        // check for correct number of datasets (12)
        $this->assertSize(Dataset::getDatasets(), 12);
        // check for correct number of features in specific dataset
        $this->assertSize(Dataset::getDatasets()['points1.json']->getFeatures(), 12);
    }
}
?>
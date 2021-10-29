<?php
namespace Grav\Plugin\LeafletTour;

class FeatureTest extends Test {

    /**
     * Prepare several features to reference for testing
     */
    protected function setup() {
        $feat0 = [
            'type'=>'Feature',
            'id'=>'feat0',
            'customName'=>'Feature 0',
            'properties'=>['name'=>'meh', 'prop1'=>'x', 'prop2'=>12],
            'geometry'=>['type'=>'Point', 'coordinates'=>[20, 20]],
        ];
        $this->feat0 = new Feature($feat0, 'name', 'Point');
        $feat1 = [
            'type'=>'Feature',
            'id'=>'feat1',
            'customName'=>'',
            'properties'=>['name'=>'Feature 1'],
            'geometry'=>['type'=>'Point', 'coordinates'=>[20, 20]],
        ];
        $this->feat1 = new Feature($feat1, 'name', 'Point');
        $feat2 = [
            'type'=>'Feature',
            'id'=>'feat2',
            'customName'=>'',
            'geometry'=>['type'=>'Point', 'coordinates'=>[20, 20]],
        ];
        $this->feat2 = new Feature($feat2, 'name', 'Point');
    }

    /**
     * Test the getName function
     * 
     * Requires: nothing
     */
    protected function testGetName() {
        // with custom name
        $this->assertEquals($this->feat0->getName(), 'Feature 0');
        // with no custom name, only name property
        $this->assertEquals($this->feat1->getName(), 'Feature 1');
        // no name, only id
        $this->assertEquals($this->feat2->getName(), 'feat2');
    }

    /**
     * Test the updateFeature method
     * 
     * Requires: nothing
     */
    protected function testUpdateFeature() {
        // replace custom name with null, valid coords, add prop, remove prop, modify prop
        $this->feat0->update(['custom_name'=>null, 'coordinates'=>[40, 60], 'properties'=>['name'=>'meh', 'prop1'=>'y', 'prop3'=>42]]);
        // Custom name is null, so the feature's name property is used
        $this->assertEquals($this->feat0->getName(), 'meh');
        // Coordinates were replaced
        $this->assertEquals($this->feat0->asJson()['geometry']['coordinates'][0], 40);
        // New property successfully added
        $this->assertEquals($this->feat0->getProperties()['prop3'], 42);
        // Property successfully removed
        $this->assertEmpty($this->feat0->getProperties()['prop2']);
        // Property successfully replaced
        $this->assertEquals($this->feat0->getProperties()['prop1'], 'y');
        // add custom name
        $this->feat1->update(['custom_name'=>'something']);
        $this->assertEquals($this->feat1->getName(), 'something');
        // replace custom name with blank, invalid coords
        $this->feat1->update(['custom_name'=>'', 'coordinates'=>[92, 92]]);
        // Custom name is blank, so the feature's name property is used
        $this->assertEquals($this->feat1->getName(), 'Feature 1');
        // Valid coordinates were not replaced with invalid ones
        $this->assertEquals($this->feat1->asJson()['geometry']['coordinates'][0], 20);
    }

    /**
     * Test the staic setValidFeature method
     * 
     * Requires: nothing
     */
    protected function testSetValidFeature() {
        // standard feature with type, properties, and geometry is recognized as valid
        $feature = ['type'=>'Feature', 'properties'=>['name'=>'meh'], 'geometry'=>['type'=>'Point', 'coordinates'=>[0,0]]];
        $this->assertNotEmpty(Feature::setValidFeature($feature, 'point'));
        // feature with only valid geometry (no type, no properties) is recognized as valid
        $feature = ['geometry'=>['type'=>'Point', 'coordinates'=>[0,0]]];
        $this->assertNotEmpty(Feature::setValidFeature($feature, 'point'));
        // valid LineString feature is not a valid point
        $feature = ['type'=>'Feature', 'properties'=>['name'=>'meh'], 'geometry'=>['type'=>'LineString', 'coordinates'=>[[0,0], [1,1], [2,2]]]];
        $this->assertNull(Feature::setValidFeature($feature, 'point'));
        // feature with invalid coordinates is not accepted
        $feature = ['type'=>'Feature', 'properties'=>['name'=>'meh'], 'geometry'=>['type'=>'Point', 'coordinates'=>[91,90.001]]];
        $this->assertNull(Feature::setValidFeature($feature, 'point'));
    }
}
?>
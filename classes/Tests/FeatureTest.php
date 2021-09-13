<?php
namespace Grav\Plugin\LeafletTour;

class FeatureTest extends Test {

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

    protected function testGetName() {
        // with custom name
        $this->assertEquals($this->feat0->getName(), 'Feature 0');
        // with no custom name, only name property
        $this->assertEquals($this->feat1->getName(), 'Feature 1');
        // no name, only id
        $this->assertEquals($this->feat2->getName(), 'feat2');
    }

    protected function testUpdateFeature() {
        // replace custom name with null, valid coords, add prop, remove prop, modify prop
        $this->feat0->update(['custom_name'=>null, 'coordinates'=>[40, 60], 'properties'=>['name'=>'meh', 'prop1'=>'y', 'prop3'=>42]]);
        $this->assertEquals($this->feat0->getName(), 'meh');
        $this->assertEquals($this->feat0->asJson()['geometry']['coordinates'][0], 40);
        $this->assertEquals($this->feat0->getProperties()['prop3'], 42);
        $this->assertEmpty($this->feat0->getProperties()['prop2']);
        $this->assertEquals($this->feat0->getProperties()['prop1'], 'y');
        // add custom name
        $this->feat1->update(['custom_name'=>'something']);
        $this->assertEquals($this->feat1->getName(), 'something');
        // replace custom name with blank, invalid coords
        $this->feat1->update(['custom_name'=>'', 'coordinates'=>[92, 92]]);
        $this->assertEquals($this->feat1->getName(), 'Feature 1');
        $this->assertEquals($this->feat1->asJson()['geometry']['coordinates'][0], 20);
    }

    protected function testSetValidFeature() {
        $f1 = ['type'=>'Feature', 'properties'=>['name'=>'meh'], 'geometry'=>['type'=>'Point', 'coordinates'=>[0,0]]];
        $f2 = ['geometry'=>['type'=>'Point', 'coordinates'=>[0,0]]];
        $f3 = ['type'=>'Feature', 'properties'=>['name'=>'meh'], 'geometry'=>['type'=>'LineString', 'coordinates'=>[[0,0], [1,1], [2,2]]]];
        $f4 = ['type'=>'Feature', 'properties'=>['name'=>'meh'], 'geometry'=>['type'=>'Point', 'coordinates'=>[91,90.001]]];
        $this->assertNotEmpty(Feature::setValidFeature($f1, 'point'));
        $this->assertNotEmpty(Feature::setValidFeature($f2, 'point'));
        $this->assertNull(Feature::setValidFeature($f3, 'point'));
        $this->assertNull(Feature::setValidFeature($f4, 'point'));
    }
}
?>
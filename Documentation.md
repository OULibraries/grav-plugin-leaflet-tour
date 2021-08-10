# Documentation

For usage instructions, please see the readme file.

## PHP Classes

### LeafletTourPlugin (leaflet-tour.php)

#### Subscribed Events

- onPluginsInitialized
- onTwigTemplatePaths
- onTwigSiteVariables
- onGetPageTemplates
- onGetPageBlueprints
- onAdminSave
- onShortcodeHandlers

#### Other Public Static Functions

- getDatasetFiles(): array
- getTourFeatures(bool $onlyPoints=false, bool $fromView=false): array
- getTourFeaturesForView(bool $onlyPoints=false): array
- getTourLocations(): array
- getTourLocationsForView(): array
- getBasemaps(): array
- getPropertyList(): array
- getTileServers(string $key = null): array|null

### Dataset

Constructor: string $jsonFilename, Data $config=null

#### Public Functions

- updateDataset(Header $header, string $datasetFileRoute): Header
- saveDataset(string $datasetFileRoute = null): void
- saveDatasetPage(string $datasetFileRoute = null): void
- getName(): string
- getNameProperty(): string
- getFeatureType(): string
- getFeatures(): array
- getProperties(): array
- asYaml(): array
- asJson(): array
- setDefaults(): void
- mergeTourData(Data $dataset, array $features): Data

#### Public Static Functions

- getJsonFile($jsonFilename): CompiledJsonFile
- createNewDataset(array $jsonArray, string $jsonFilename): void
- getDatasetList(): array
- getDatasets(): array
- resetDatasets(): void

### Feature

Constructor: array $jsonData, string $nameProperty, string $type='point'

#### Public Functions

- isValid(): bool
- setDatasetFields(array $featureData): void
- update(array $featureData): void
- getName(): string
- getPopup(): string|null
- getId(): string
- asJson(): array
- asGeoJson(): array
- asYaml(): array

#### Public Static Functions

- buildFeatureList(array $features, $nameProperty, string $type=null): array
- buildJsonList(array $features): array
- buildYamlList(array $features): array
- buildConfigList(array $features): array

### LeafletTour

Constructor: array $config

#### Public Functions

- getTour($page): Tour

### Tour

Constructor: $page, Data $config

#### Public Functions

- getBasemaps(): array - [file => [file, bounds, minZoom, maxZoom]]
- getAttribution(): array - [[name, url]]
- getExtraAttribution(): array
- getViews(): array - [viewId => [basemaps, onlyShowViewFeatures, removeTileServer, noTourBasemaps, zoom, center, features]]
- getDatasets(): array - [id => [legendAltText, iconOptions, pathOptions, pathActiveOptions]]
- getFeatures(): array - [id => [type, properties (name, id, dataSource, hasPopup) geometry (type, coordinates)]]
- getLegend(): array - [[dataSource, legendText, iconFile, iconWidth, iconHeight, iconAltText]]
- getPopups(): array - [id => [id, name, popup]]
- getViewId($view): string
- getViewPopups(string $viewId): array - [[id, name]]
- getOptions(): array

#### Public Static Functions

- getViewPopup(string $featureId, string $buttonId, string $featureName): string
- hasPopup($feature, $tourFeatures): bool

### Utils

#### Public Static Functions

- isValidPoint($coords, $reverse = false): bool
- isValidMultiPoint($coords): bool
- isValidLineString($coords): bool
- isValidMultiLineString($coords): bool
- isValidPolygon($coords): bool
- isValidMultiPolygon($coords): bool
- areValidCoordinates($coords, string $type): bool
- setValidType(string $type): string
- setBounds($bounds): array
- getPageRoute(array $keys): string
- getDatasetRoute(): string
- getTourRouteFromTourConfig(): string
- getTourRouteFromViewConfig(): string
- parseDatasetUpload(array $fileData): array
- generateShortcodeList(array $features, array $datasets): string
- createPopupsPage(string $tourTitle): void
- getAllPopups(string $tourRoute): array

## JavaScript

### Major Variables

```js
var tourState = {
    view: null,             // string
    tmpView: null,          // string
    basemaps: [],           // array
    activeBasemaps: [],     // array
    mapAnimation: true,     // bool
    scrollyPos: 0,          // int
    mapNeedsAdjusting: true // bool
}

var tourOptions = {
    bounds: [],             // array
    maxZoom: 16,            // int
    minZoom: 8,             // int
    removeTileServer: true, // bool
    showMapLocationInUrl: true,     // bool
    stamenTileServer: null, // string
    tileServer: null,       // string
    tourMaps: [],           // array
    wideCol: false,         // bool
    maxBounds: null,         // array
}
```

### Functions

- modalIcon (`Array` options): L.Icon.ModalIcon
    - Leaflet Icon Options + id, role
- createMarker(`Array` props, `LatLng` latlng, `Array` dataset): L.Marker
- setFeatureInteraction()
- setIconInteraction()
- setPathInteraction()
- setActivePath(`string` id)
- endActivePath(`string` id)
- setupFeature(`L.Feature` geoJsonFeature, `L.Layer` layer)
- createTooltip(`string` name, `string` datasetId, `L.Layer` layer)
- resetAllLabels()
- setBasemaps()
- checkBasemaps()
- adjustMap()
- switchToMap(`string` focusElement)
- switchToContent(`string` focusElement)
- toggleHideFeature(`Array` feature)
- toggleHideNonViewFeatures(`string` viewId, `bool` hide)
- enterView(`string` id)
- exitView()

## Attribution

### JavaScript Libraries

The following JavaScript Libraries are used in this plugin:

- [Leaflet](http://leafletjs.com) 1.6.0 - a JS library for interactive maps.
- Leaflet Libraries
    - [Leaflet Rotated Marker](https://github.com/bbecquet/Leaflet.RotatedMarker)
    - [Leaflet Pattern](https://github.com/teastman/Leaflet.pattern)
    - [Leaflet Hash](https://github.com/mlevans/leaflet-hash)
- [RBush](https://github.com/mourner/rbush)
- [Labelgun](https://github.com/Geovation/labelgun)
- [Intersection Observer](https://unpkg.com/intersection-observer@0.12.0/intersection-observer.js)
- [Scrollama](https://github.com/russellgoldenberg/scrollama)
- [Stamen Maps](http://maps.stamen.com)

### Other Resources

<!-- TODO: Fill out -->

- qgis2web
- Leaflet documentation (often descriptions in config and readme come from this documentation verbatim)
- QGIS (sort of)
- ArcGIS - default basemap
- leaflet marker icon (or would this just be part of the Leaflet library?)
- font awesome
- Grav lol
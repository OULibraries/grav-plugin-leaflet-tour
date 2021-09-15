# Leaflet Tour Plugin

<!-- TODO: Plugin screenshot -->

**Leaflet Tour** is a Grav plugin designed to enable students, researchers, and anyone else interested to tell stories using a combination of narrative text and accessible interactive maps.

<!-- TODO: demo website -->

What can the plugin do?

- creates a guided tour using text content and geographical data
- can use points, lines, and shapes (GeoJSON types Point, LineString, MultiLineString, Polygon, MultiPolygon)
- can add popup information to features (including images)
- meets accessibility standards
- provides options for customizing various aspects of the display
- allows providing multiple tours and/or standard pages of content

## Requirements

The plugin requires Grav, which can be installed via a web hosting service. It also requires using a specific Grav theme (Grav Theme Basic)

- Geographic data in GeoJSON format (as .json or .js files)
- Grav version 1.7.0 or higher
- PHP version 7.4 or higher
- Grav Theme Basic (enabled)
- Shortcode Core plugin
- Admin Panel plugin

The required theme has additional plugins listed that, while not required, are strongly recommended. Please check out the documentation for the theme to see these plugins as well as important information for configuring them.

<!-- TODO: Link to theme readme -->

<!-- TODO: Should I include info on why a particular theme is required? -->

## Installation

Installing the Leaflet Tour plugin can be done in one of two ways. The GPM (Grav Package Manager) installation method enables you to install the plugin with the admin panel or a terminal command, while the manual method enables you to do so via a zip file.

### GPM Installation (Preferred)

The simplest way to install this plugin is via the admin panel. To do this, go to the Plugins tab, click the add button, look up this plugin, and then click install.

You can install this plugin via the [Grav Package Manager (GPM)](https://learn.getgrav.org/cli-console/grav-cli-gpm) through your system's Terminal (also called the command line). From the root of your Grav install type:

```
bin/gpm install leaflet-tour
```

This will install the Leaflet Tour plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your-site/grav/user/plugins/leaflet-tour`.

### Manual Installation

To install this plugin, download the zipfile from the [plugin repository](TODO:Link) and unzip it under `/your-site/grav/user/plugins`. Then, rename the folder to `leaflet-tour`. (You can also find the plugin files via [GetGrav.org](https://getgrav.org/downloads/plugins).) The filepath to the plugin should now be: `/your-site/grav/user/plugins/leaflet-tour`.

## Updating

As development for the Leaflet Tour plugin continues, new versions may become available that add additional features and functionality, improve compatibility with newer Grav releases, and generally provide a better user experience. As with installation, updating Leaflet Tour can be done through Grav's GPM system (via terminal or the Admin panel) or manually.

Please note: Any changes you have made to any of the files in the plugin will be overwritten. Any files located elsewhere (for example, a .yaml settings files placed in `user/config/plugins`) will remain intact.

### GPM Update (Preferred)

The simplest way to update this plugin is via the admin panel. To do this, go to the Plugins tab and check for updates. The dashboard will indicate if any plugins have available updates and will allow you to update them individually or to update all of them at once.

You can also update this plugin via the Grav Package Manager (GPM). You can do this by navigating to the root directory of your Grav install using your system's Terminal (also called the command line) and typing the following:

```
bin/gpm update leaflet-tour
```

This command will check your Grav install to see if the Leaflet Tour plugin is due for an update. If a newer release is found, you will be asked whether or not you wish to update. To continue, type `y` and hit enter. The plugin will automatically update and clear Grav's cache.

### Manual Update

To manually update Leaflet Tour:

1. Delete the `/your-site/user/plugins/leaflet-tour` directory. Data you have uploaded is stored in your `user/data` directory, and therefore will be preserved.
2. Download the new version of the Leaflet Tour theme from either [GitHub](TODO:Link) or [GetGrav.org](https://getgrav.org/downloads/plugins).
3. Unzip the zip file in `your-site/user/plugins` and rename the resulting folder `leaflet-tour`.
4. Clear the Grav cache. The simplest way to do this is by going to the root Grav directory in the terminal and typing `bin/grav clear-cache`. If you are using the admin panel, there is also a button to clear the cache in the navigation sidebar.

## Getting Started

The standard workflow will look like this:

1. Prepare datasets and other files
2. Configure plugin settings
3. Configure dataset settings
4. Add a tour
5. Add views

### Prepare Datasets and Other Files

You will need one or more datasets with geographic information. The plugin currently accepts JavaScript (.js) or JSON (.json) files that follow the [standard GeoJSON format](https://datatracker.ietf.org/doc/html/rfc7946).

The JSON object should have:
- `"type": "FeatureCollection"`
- A `"name"` property - this is optional, but useful
- A list of features

Each feature should have:
- `"type": "feature"`
- `"gemoetry"` with `"type"` and `"coordinate"` fields
- A geometry type of Point, LineString, MultiLineString, Polygon, or MultiPolygon (this should be the same for all features in the dataset)
- `"properties"` with one or more fields (recommended for there to be some type of name field - you could have anything that will help you identify the object and then add custom names to be displayed on the site, but you might as well save yourself the trouble)

JavaScript files are a bit more complicated. JSON files are recommended unless you are using data from a qgis2web export, which stores the GeoJSON data in JavaScript files. These files can be found in the export's `data` folder and should look like `var json_varNameHere = { "GeoJSON" }`, where `"GeoJSON"` is a valid GeoJSON object.

- The JavaScript file should consist of the variable holding the GeoJSON data and nothing else (although comments at the beginning should be fine).
- The variable holding the GeoJSON data must begin with `json_` and use only letters, numbers, and underscores.

In order to upload JavaScript files, you will need to modify the site's security settings:

1. Go to Configuration from the Admin panel.
2. Choose the Security tab (between Media and Info).
3. Find Dangerous Extensions under Uploads Security. This should have a list with several extensions, including `js`.
4. Click on `js` and press delete.
5. Save.

Other files:
- TODO: how to change csv/spreadsheet/shapefile dataset into GeoJSON

### Configure Plugin Settings

Make sure the plugin is enabled. It should be by default when you added it through the admin panel, but it never hurts to check. A complete list of plugin configuration settings can be found further down in this document, but here is a quick overview of the most important ones:

On the General tab:
- Upload the dataset files prepared in the previous step. Make sure these files have been fully uploaded (a checkmark should appear by each file) before saving the page.
- Upload any marker icon files you want to use for Point datasets.
- Modify attribution settings. Most likely all you will want to do is remove any items from the Additional Resources list that are not applicable, at least to start with.

On the Basemaps tab:
- Either select a tile server from the list of provided options, go with the default URL provided, or replace the Tile Server URL with your preferred tile server.

On the Popup Images tab:
- If you already know of some images you will want to include in feature popup content, go ahead and upload them here. You can always come back and add more later.

### Configure Dataset Settings

When you upload a new dataset, several things happen:
- The content is checked to see if it is valid GeoJSON. If not, it is ignored and nothing else happens.
- A best guess is determined for which property contains the default feature names.
- An id is generated for each feature, which will be used to reference them in dataset, tour, and view config pages.
- If no name is provided, the dataset is given a name.
- The modified JSON is saved as a new JSON file in `user/data/leaflet-tour/datasets/`.
- A dataset page is created in the datasets folder (the folder is created if it does not exist), which can then be edited from the admin panel.

The datasets pages can be found on the Pages tab inside the datasets folder. These pages will not display publicly, but they provide an interface for modifying settings. A complete list of dataset configuration settings can be found further down in this document, but here is a quick overview of the most important ones:

On the Options tab:
- Add a description to go on the legend. Ideally this should be pretty short, but if it is not you can add a shortened version for the Legend Alt Text.

On the Features tab:
- Make sure that the name property is correct. If it is not, select the correct property from the dropdown.
- Add custom names and popup content to features as needed. The popup content contains normal markdown just like you would include for page content. You can include images added in the plugin configuration by adding `image://` in front of the image name. Example: `![Image alt text](image://image_name.jpg)`

On the Icon tab:
- Only deal with this tab if the dataset features are points.
- Choose an icon file from the list of marker icons you uploaded in the plugin configuration, unless you want to use the default Leaflet marker.
- You can probably ignore most, if not all, of the other settings. If you aren't sure what one does or don't feel strongly about it, leave it blank and don't worry about it.

On the SVG tab:
- Only deal with this tab if the dataset features are ***not*** points.
- Choose a stroke color, unless you want to use the default blue.
- As with the icon settings, you can probably ignore most, if not all, of the other settings. In general, the defaults will be what you want.

Note: If you are using this dataset in multiple tours and want the legend info or icon/svg display to be different depending on the tour, it is possible to override all legend, icon, and svg/path settings in the tour config.

### Add a Tour

Tours display a map with various geographic features alongside some narrative text. As users scroll through this narrative, the map will change based on what views you have added to the tour. Specific features may be highlighted by removing all other features (temporarily) from the map and zooming/panning the map to the features.

To create a tour, add a new page and choose the `tour` template. You will probably want the parent page to be `<root>` (i.e. `/`).

- The page title will become a heading level one on the tour's narrative column.
- Add background information or other content introducing the tour to the page content.
- After the tour is ready, you will want to add some number of views to section out content.

A complete list of tour configuration settings (the Tour tab) can be found further down in this document, but here is a quick overview of the most important ones:

- Add datasets uploaded via the plugin configuration page. There are several overrides available, though these are not generally recommended.
- Set a starting position if you do not want the starting bounds to be automatically determined based on the included features.
- Decide if the tour should include a legend, and if users should be able to show/hide the various datasets from the legend.
- Decide if the default will be for each view to only display the features added to that view or to display all features.
- Decide if you want to use shortcodes to decide where buttons to open popup content will belong, or if you want all popup buttons for features included in a view to be listed at the bottom of the view.

### Add Views

Views are modular components inside tours. What this means is that views are "modules" rather than pages, their parent should always be a tour page, and they will not be displayed as individual pages on the website. Instead, view content will be included in the tour text, with the page title included as a heading level two.

Note: When creating a new page, the parent will be set to whatever page you are currently on (or the root if you are not editing a page) by default. If you create a new view from a view page (e.g. you created the first view and now you want to add another), this means that you will need to change the parent to make sure it is the tour page. Sometimes, the page creation dialog box will still show that the parent is the view after you have changed this. If you ignore this and create the page, however, you should find that the tour is the parent. If you did not remember to change the parent, or if something went wrong, you can always change the page parent from the Advanced tab.

A complete list of view configuration settings (the View tab) can be found further down in this document. While you may want to set the starting position if you do not want the starting bounds to be automatically generated, the most important configuration option is the features list. Choose any features you want to single out (the dropdown should have all valid features from datasets included in the tour). These features will determine what popup buttons are added to the view content, what shortcodes are generated for easy copy & paste, the starting position and bounds for the map when entering the view, and what features remain on the map if _"Only Show Current View Features"_ is enabled.

Shortcodes: After adding some features and saving, the Content tab will include a list of shortcodes that you can copy & paste into the view content. These shortcodes will generate popup buttons for the various view features, which allows you to include the button when you discuss that feature in the view, rather than having it as part of a list at the end of the view. Make sure you have turned off _"List View Popup Buttons"_ either in the tour or view configuration is you use these, or you will be including the popup buttons twice.

You may want to turn on folder numeric prefix if you are having trouble ordering your views. This option can be found on the Advanced tab, and once turned on, it will allow you to drag and drop pages to change their order.

## Updating and Deleting Content

The current process for updating and deleting various types of content is not very good. This section will detail the process and specifications for future develoopment.

### Update Datasets

1. Upload the new file to dataset update field (not the data files field).
2. Save.
    1. The new file will be parsed and compared to existing data.
    2. Features and various properties will be updated based on the new file.
    3. The JSON file wil be updated to match the updated dataset.
    4. The original upload file will be replaced by the update file.

### Delete Datasets

1. Delete the file from the data files fields.
2. Save.
    1. The original upload file will be gone.
    2. The JSON file created from the original upload will be removed.
    3. The dataset page will be moved to the deleted_datasets folder.
3. You can remove the old dataset page from deleted_datasets whenever you like.

### Delete Tour

1. Delete the tour page.
2. Either the popup page associated with the tour will be removed automatically, or you will need to delete this page manually, as well.

## Accessibility

This plugin has been designed to allow content creators to make interactive maps and tell stories that are as accessible as possible to all viewers, including those with disabilities. Please contact Theo Acker or submit an issue on GitHub if you run into any accessibility issues with the tours.

Of course, the plugin can only do so much. It is entirely possible to use this to create inaccessible content if you do not follow good accessibility practices. The purpose of this section is to inform you of any potential problem areas.

### Accessible Configuration Options

- Ensure that all content, including page and popup content follow all the standard accessibility rules (e.g. good link text, alt text on images, etc.).
- Don't upload basemap images with important text. Because these are images, the text would be missed entirely. Instead, include important text in page or popup content.
- Provide a short but descriptive tag for the legend description, or provide legend alt text if the description needs to be longer.
- Make sure icon images and svg options have good contrast and size, are colorblind-friendly, and will show up well against the map.
- Note that tours begin with a heading level 1, and views and popup content begin with heading level 2. Thus, you will only want to use heading levels 3-4.

## Configuration Options

The configuration options for the plugin and included page templates are detailed below. The label associated with the option in the admin panel interface will be provided, along with the yaml name in parantheses. Additional notes are included as necessary.

Some of the options listed here are readonly. Please do not modify them in expert mode or directly from the file, as they really should not be modified.

### Important Notes

#### Coordinates

As a matter of standards, all coordinates will be provided as `[longitude, latitude]`. This is how GeoJSON stores coordinates, since longitude corresponds to the x-value and latitude corresponds to the y-value (and x always comes before y in `[x, y]` pairs). Therefore, for consistency, the plugin will also use this convention wherever applicable.

All coordinates must be provided in degrees. That is, longitude has a range from -180 to 180, and latitude has a range from -90 to 90. If you have coordinates in some other form, you can find tools to easily convert them using the internet.

#### Tile Servers

The tile server is the default basemap of the Leaflet tour. Tile servers provide a map that is separated into tiles with different zoom levels. This allows Leaflet to load only the necessary tiles for the portion of the map shown at any given time. Additionally, because tiles are provided for different zoom levels with different levels of detail, a tile server can provide detailed images when zoomed in without reducing performance of the site by loading unnecessary detail when zoomed out.

You can set the tile server for all tours using the plugin configuration, but you can also customize this setting for individual tours. A dropdown has been provided to select a tile server if you want to use one of the provided options. Currently these are limited to Stamen maps, but more may be added in the future. If you wish to provide a URL to a different tile server instead, make sure the dropdown has been set to `None`.

Stamen Maps:
- Watercolor (stamenWatercolor)
- Toner (stamenToner)
- Terrain (stamenTerrain)

#### Zoom Levels

Leaflet zoom levels range from 0 to 28, with 0 being the furthest zoomed out. At a zoom of 0, the world is shown as a 256px by 256px image. Each increase in zoom level divides each 256x256 tile into four 256x256 tiles.

Most tile servers offer tiles up to zoom level 18. If you are adding a tile server, you may want to check its maximum native zoom and use that as the maximum zoom for the tour.

You will also see min and max zoom for the additional basemap images. In these cases, the values determine the range of zoom levels at which the basemap can be shown. That is, if you zoom out further than the minimum zoom of a given basemap, that basemap will disappear until you zoom back in.

#### Attribution

Each tour footer includes an attribution list for all resources used. You can use this to add attributions for any additional resources you use. The basic attribution list provides a place for text and a place for a URL. The HTML attribution list provides only a textbox where you can paste HTML code for attributions provided by resources (or create your own).

- By default, the plugin config contains basic attributions for QGIS, qgis2web, and Leaflet. Leaflet is included because it is required for the map to run. If you are not using QGIS or qgis2web, you will probably want to remove these.
- Attributions for tile servers and basemap images will be included in the footer attribution. There is no need to put attribution info in the basemap information list and then repeat it in the plugin or tour.
- Any items added to the tour config will be added to items from the plugin when generating that tour's footer. In other words, the tour attribution does not overwrite the plugin attribution.

#### Shortcodes

The view configuration Content tab includes a list of shortcodes. These will be updated whenever the configuration is saved to provide the shortcode needed for any features added to the view features list. You can copy and paste these codes into the content in order to manually determine where the button to open that particular feature's popup content will be located. This allows you to put these buttons in sensible places where you actually mention the feature in the view content, rather than as part of a list at the very end of the view content. Make sure to turn off the option to list view popups (either tour config or view config) if you are making use of these shortcodes!

<!-- TODO: Describe shortcode structure -->

### Plugin Configuration

If editing this manually, make sure to copy the `user/plugins/leaflet-tour/leaflet-tour.yaml` file to the `user/config/plugins/` folder and modify the copy. If you modify the original plugin file directly, any changes will be lost with future updates.

| Option | Default | Values | Description |
|---|---|---|---|
| Plugin status (status) | Enabled (true) | Enabled (true) or Disabled (false) | Determines whether the plugin is enabled at all - make sure this is always true! |
| Data Files (data_files) | None | File upload (multiple, limit 25) | File upload to the "user/data/leaflet-tour/basemaps/" folder. Files should be valid JavaScript or JSON files as specified in the section on preparing datasets. |
| Update Datasets (update_data) | Disabled (false) | Enabled (true) or Disabled (false) | Determines whether or not existing dataset files will be updated. See the section on updating and deleting content for more information. |
| Markers (marker_files) | None | File upload (multiple) | File upload for image files to the "user/data/leaflet-tour/images/markers/" folder. Images added here can be selected for use as marker icons for datasets with Point features. |
| Marker Shadows (shadow_files) | None | File upload (multiple) | File upload for image files to the "user/data/leaflet-tour/images/markerShadows/" folder. Images added here can be selected for use as marker icon shadows for datasets with Point features. |
|  Wide Narrative Column (wide_column) |  Disabled (false) |     Enabled (true) or Disabled (false) |     Determines the width of the narrative column.   The column will be 33% of the page width by default. Enabling this option   will change it to 40%. Note that the narrative column will always be 100% on   mobile.    |
|     Show Map Location in URL   (show_map_location_in_url) |     Enabled (true) |     Enabled (true) or Disabled (false) |     Determines whether the zoom level and center   coordinates will be included in tour URLs.    |
|     Simple Attribution List   (attribution_list) |     Entries for Leaflet,   qgis2web, and QGIS |     List |     List items take a text   value (required) and URL value (optional). They will be displayed in the   footer as either plain text or links. See the defaults at the end of this   section for examples.    |
|     HTML Attribution List   (attribution_html) |     None |     List |     List items take only a   text value. Use this for resources that provide HTML code for an attribution.   They will be displayed in the footer after any items in the simple   attribution list.    |
|          Select a Tile Server   (tile_server_select) |     None |     Dropdown featuring list   of pre-defined tile servers |     Determines which, if any,   of the provided tile servers will be used. Currently only Stamen maps   (watercolor, toner, and terrain) are included.    |
|     Tile Server URL   (tile_server.url) |     [ArcGIS World](https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}) |     Text |     If no tile server is   selected from the dropdown, this defines the URL that will be used to pull   tiles from.    |
|     Tile Server Attribution   (tile_server.attribution_text) |     ArcGIS World |     Text |     Identifies the tile   server provided in the Tile Server URL and adds it to the footer attribution   list. Will be ignored if a tile server is selected from the dropdown.    |
|     Tile Server Attribution   URL (tile_server.attribution_url) |     [ArcGIS World](https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer) |     Text |     If provided, the   attribution text above will become a link to this URL. Will be ignored if no   text is provided.    |
|     Custom Basemap Images   (basemap_files) |     None |     File upload (multiple) |     File upload for image   files to the "user/data/leaflet-tour/basemaps" folder. Images added   here (and provided with additional data as described below) can be included   in tours and views as additional basemaps. You will have to save the plugin   configuration before you can add the additional required information for any   new uploads.    |
|     Basemap Information   (basemaps) |     None |     List | A list of necessary information   about each basemap. Only images that have this information will be valid   choices to add to tours and views. A description of each of the fields is   provided below. |
| Popup   Images (popup_image_files) | None | File upload (multiple) | File upload for image files to   the "user/images" folder. Images added here can be referenced in   the popup content markdown in dataset and tour configuration pages by adding   `image://` in front of the filename. For example: `![Image alt text](image://image_name.jpg)` |

Notes on a few fields:
- Detailed instructions about datasets for Data Files can be found in the section on preparing datasets.
- More information about tile servers can be found in the configuration note about tile servers and basemap images.
- Custom basemap images can take some time to load, especially if they are high quality. It is strongly recommended to rely first and foremost on a tile server.

The following fields can be overwritten for individual tours:
- Wide Narrative Column
- Show Map Location in URL

#### Basemap Information

The following fields are required for items in the Basemap Information list: Image (file) and Bounds (all four values). Make sure you save after uploading a new basemap image. The dropdown will not add new options until after the page has been saved.

|            Option |        Default |        Values |        Description    |
|---|---|---|---|
| Select   the Basemap Image (file) | None | Dropdown featuring list of   uploaded basemap images | Defines the basemap image that   the data provided is for. |
| Bounds -   North (bounds.north) | None | Number from -90 to 90 | Latitude (degrees) for the   Northern edge of the basemap image. (This will typically be the top of the   map.) |
| Bounds -   South (bounds.south) | None | Number from -90 to 90 | Latitude (degrees) for the   Southern edge of the basemap image. (This will typically be the bottom of the   map.) |
| Bounds -   East (bounds.east) | None | Number from -180 to 180 | Longitude (degrees) for the   Eastern edge of the basemap image. (This will typically be the right side of   the map.) |
| Bounds -   West (bounds.west) | None | Number from -180 to 180 | Longitude (degrees) for the   Western edge of the basemap image. (This will typically be the left side of   the map.) |
| Max Zoom   (zoom_max) | 16 | Number from 0 to 28 | Determines the zoom level at   which the basemap is displayed. Zooming in closer than this will temporarily   hide the image. This is useful to prevent a basemap being zoomed in too close   and becoming blurry. |
| Min Zoom   (zoom_min) | 8 | Number from 0 to 28 | Determines the zoom level at   which the basemap is displayed. Zooming out further than this will   temporarily hide the image. This is useful for hiding basemaps when they   become too small. |
| Attribution   Text (attribution_text) | None | Text | Identifies the basemap provided   and adds it to the footer attribution list. Will only be used if the basemap   has been added to the tour or to one of the tour's views. |
| Attribution   URL (attribution_url) | None | Text | If provided, the attribution   text above will become a link to this URL. Will be ignored if no text is   provided. |

#### Defaults

```yaml
enabled: true
leaflet_tour: true    # Hidden field, do not modify or remove!
update_data: false
wide_column: false
show_map_location_in_url: true
attribution_toggle: true
attribution_list:
  -
    text: Leaflet
    url: 'https://leafletjs.com/'
  -
    text: qgis2web
    url: 'https://github.com/tomchadwin/qgis2web'
  -
    text: QGIS
    url: 'https://qgis.org/'
tile_server_select: 'none'
tile_server:
    url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
    attribution_text: 'ArcGIS World'
    attribution_url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer'
```

#### Editing YAML

- Any file uploads will be difficult to edit directly as yaml. Avoid doing so.
- The Tile Server selection dropdown displays the name of the tile server on the admin panel, but stores a slightly different identifier. See the list of included tile servers in the features section of this document.
- The dropdown for image files in the Basemap Information list displays and stores the name of the image file (including the image extension).

### Dataset Configuration

|            Option |        Default |  |        Values |        Description    |
|---|---|---|---|---|
| Dataset   File (dataset_file) | JSON filename of the   dataset the page was created for |  | Read Only | Defines which dataset all of   the settings will be applied to. |
| Dataset   Name (title) | The "name"   included in the dataset file if it exists. Otherwise the filename. |  | Text | The name of the dataset and the   title of the page. |
| Folder   Name (folder) | Same as page title |  | Text | The name of the folder holding   this page (not stored in the header config). |
| Description   for Legend (legend_text) | None |  | Text | A short description of the   dataset that will be included in the legend. |
| Legend   Alt Text (legend_alt) | None |  | Text | Ideally the legend description   will be quite short, but if it is not, a shorter description can be provided   here to be included in the alt text for each feature on the map. If this is   empty, will default to the legend description. |
| Name   Property (name_prop) | Feature property   "name" if it exists. Otherwise first feature property starting or   ending with "name" if one exists. Otherwise the first feature   property. |  | Dropdown featuring list of   properties provided for dataset features | The property that provides the   default name/identifier for each feature. |
| Features   (features) | All valid features   from the dataset |  | List | A list of all features in the   dataset. Although buttons exist, features cannot be added or removed using   the dataset configuration page. |

Notes on a few fields:
- When editing the legend description or alt text, keep in mind that the alt text for each feature will read as "Feature Name, legend alt text." If the feature has a popup, this alt text will end with "open popup."
- The Name Property default will be determined by looking through a list of properties for features in the dataset. If a property called `name` (not case-sensitive) is present, this will be chosen. If not, the first of any properties starting or ending with `name` will be chosen. If no options are available, the first property will be chosen. Make sure every feature has a value for its name property! A feature without a name will be considered invalid.

The following fields can be overridden by individual tours:
- Legend (description and alt text)
- Icon Alt Text
- All icon options
- All SVG options

#### Features List

- If you try to add a feature to the Features list, you will have to remove that feature before the dataset page will successfully save. If you remove a feature from the list, the feature will be returned when the page is saved.
- Popup content in the Features list may not show up until you click inside the text area. Don't panic if it initially looks like your popup content disappeared!
- You can add images to the popup content. Check the section on configuring dataset settings under Getting Started for more information.

|     Option |     Default |     Values |     Description    |
|---|---|---|---|---|
| Name   (name) | Custom name if it   exists, otherwise the value of the feature's name property | Read Only | Identifies the feature this   list item corresponds to. |
| Custom   Name (custom_name) | None | Text | A custom name that can be used   to identify the feature instead of the value of its name property. The custom   name will not overwrite any feature properties in the datasest. |
| Popup   Content (popup_content) | None | Markdown | Content that will be displayed   in a popup for the feature. This is the same kind of markdown editor used for   normal page content. Users can open feature popups by clicking on the feature   from the map, or by clicking on a provided popup button in the tour narrative   column. |

#### Icon Options

- These options only apply to datasets with Point features. The fields come from [Leaflet's Icon Options](https://leafletjs.com/reference-1.7.1.html#icon-option).
- There are two sets of defaults for these options. The first set applies if you select an uploaded image to use as the marker icon. The second set applies if you do not, which means that the default Leaflet marker icon will be used.

Icon Alt Text (icon_alt): A brief description of the icon image itself. This is not as important as legend text/alt text, but it can be nice, especially if the icon has some significance to the dataset.

|            Option |        Default (Image file provided) | Default (for default Leaflet   marker icon) |        Values |        Description    |
|---|---|---|---|---|
| Icon   Image File (icon.file) | None | None | Dropdown of images from the   "user/data/leaflet-tour/images/markers" folder | File selection dropdown for   images added to the images/markers folder. If no image is selected, the   default Leaflet marker will be used. The defaults provided for the various   options may change depending on whether or not a custom icon has been chosen. |
| Width   (icon.width) | 14 | 25 | Number (minimum of 1) | Width of the icon (in pixels) |
| Height   (icon.height) | 14 | 41 | Number (minimum of 1) | Height of the icon (in pixels) |
| Icon   Anchor (icon.anchor_x and icon.anchor_y | None | (12, 41) | Number | The coordinates of the   "tip" of the icon (relative to its top left corner). The icon will   be aligned so that this point is at the marker's geographical location.   Centered by default. The x value moves the icon to the left, and the y value   moves the icon up. If a custom image is used, both x and y values must be   provided or the value will be ignored. |
| Tooltip   Anchor X (icon.tooltip_anchor_x and icon.tooltip_anchor_y) | (7, 0) | (-12, 20) | Number | The coordinates of the point   from which tooltips will "open", relative to the icon anchor. If   changing the icon width, it is recommended to adjust the tooltip x value to   be half the width. The x value moves the tooltip to the right, and the y value   moves the tooltip down. |
| Shadow   Image File (icon.shadow) | None | default Leaflet shadow icon | Dropdown of images from the   "user/data/leaflet-tour/images/markerShadows" folder | File selection dropdown for   images added to the images/markerShadows folder. |
| Shadow   Width (icon.shadow_width) | None | 41 | Number (minimum of 1) | Width of the shadow icon (in   pixels). Ignored if there is no shadow icon. |
| Shadow   Height (icon.shadow_height) | None | 41 | Number (minimum of 1) | Height of the shadow icon (in   pixels). Ignored if there is no shadow icon. |
| Shadow   Anchor X (icon.shadow_anchor_x and shadow_anchor_y) | None | None | Number | The coordinates of the   "tip" of the shadow (relative to its top left corner). Defaults to   the coordinates of the icon anchor. Both the x and y values must be provided   for these settings to be used. |
| Class   Name (icon.class) | None | None | Text | A class name that can be used   to select the icon when adding custom CSS. It will be assigned to both icon   and shadow images. |
| Retina   Icon Image (icon.retina) | None | default Leaflet retina icon | Dropdown of images from the   "user/data/leaflet-tour/images/markers" folder | File selection dropdown for   images added to the images/markers folder. Optional. Used for Retina screen   devices. |

#### SVG Options

- These options only apply to datasets with non-Point features. The fields come from [Leaflet's Path Options](https://leafletjs.com/reference-1.7.1.html#path-option).
- There are four additional options that are not included in the admin configuration. These are lineCap, lineJoin, dashArray, and dashOffset. You can enter expert mode to add these if you would like to customize them. Check out the Leaflet documentation (link above) for information about the default values and what the options do.
- SVG Active options are identical to the SVG options. When a feature becomes active (on focus or hover) any styles provided here will override the normal style (so if no SVG Active options were set, hovering over a feature would change nothing).

|     Option |     Default (svg) | Default (svg_active) |     Values |     Description    |
|---|---|---|---|---|
| Draw   Stroke (svg.stroke) | Enabled (true) | None | Enabled (true) or Disabled   (false) | Whether to draw stroke along   the path. Set it to false to disable borders on polygons. |
| Stroke   Color (svg.color) | "#3388ff" | None | Color | The color of the stroke/border.   You can use the color picker or provide a hex color code. |
| Stroke   Width (svg.weight) | 3 | 5 | Number (minimum of 1) | The width of the stroke/border   in pixels. |
| Stroke   Opacity (svg.opacity) | 1 | None | Number from 0 to 1 | Opacity of the stroke/border. 0   is completely transparent and 1 is completely opaque. |
| Use Fill   (svg.fill) | Enabled (true) | None | Enabled (true) or Disabled   (false) | Whether to fill the path with   color. Set it to false to disable filling on polygons. |
| Fill   Color (svg.fillColor) | None | None | Color | The fill color of the polygon.   Defaults to the stroke color. |
| Fill   Opacity (svg.fillOpacity) | 0.2 | 0.4 | Number from 0 to 1 | The opacity of the polygon   fill.  0 is completely transparent and   1 is completely opaque. |

#### Defaults

```yaml
routable: 0         # Hidden field, do not modify or remove! Ensures the page cannot be reached publicly through the internet.
visible: 0          # Hidden field, do not modify or remove! Ensure the page will not show up on the navigation menu of the site.
svg:
    stroke: true
    color: '#3388ff'
    weight: 3
    opacity: 1
    fill: true
    fillOpacity: 0.2
svg_active:
    weight: 5
    fillOpacity: 0.4
```

#### Editing YAML

- There are several hidden and read only values that will become editable when you interact directly with the YAML. Do not modify or remove these!
- Folder Name will not be included in the YAML content.
- Name Property should have the same value on the admin panel and in the YAML.
- Image dropdowns should also have the same value on the admin panel and in the YAML (name of the image file).

### Tour Configuration

|     Option |     Default |     Values |     Description    |
|---|---|---|---|
| Tour   Data (datasets) | None | List | A list of all datasets to   include in the tour. Fields are described in more detail in the next table. |
| Starting   - Central Location (start.location) | None | Dropdown of all Point features   from datasets included in the tour | A feature that provides the   coordinates of the starting center point. Overrides longitude and latitude   values shown below. Requires distance to form starting bounds. |
| Starting   - Center Longitude (start.long) | None | Number from -180 to 180 | Longitude (degrees) of the   starting center point. Requires latitude and distance to form starting   bounds. |
| Starting   - Center Latitude (start.lat) | None | Number from -90 to 90 | Latitude (degrees) of the   starting center point. Requires longitude and distance to form starting   bounds. |
| Distance   from Starting Center (start.distance) | None | Number (minimum 0) | A distance (degrees) that will   be used as the radius of how far out from the center point should be included   within the starting bounds. Requires longitude and latitude or location. |
| Bounds   (start.bounds.north, start.bounds.south, start.bounds.east,   start.bounds.west) | None | Hidden option | Defines the bounds on the   starting view. Overrides other start options provided. Can be edited in   expert mode. |
| Include   Legend (legend) | Enabled (true) | Enabled (true) or Disabled   (false) | Whether the tour should provide   a legend with the map. Only datasets with legend descriptions will be   included. If no datasets have legend descriptions, this setting will be   ignored. |
| Allow   Toggling Datasets in Legend (legend_toggles) | Disabled (false) | Enabled (true) or Disabled   (false) | Whether datasets in the legend   should have checkboxes allowing users to show/hide dataset features in the   tour. Requires a legend. |
| Only   Show Current View Features (only_show_view_features) | Disabled (false) | Enabled (true) or Disabled   (false) | Whether all features not   included in a view should be hidden when that view is entered. Ignored if the   view has no features. |
| List   View Popup Buttons (list_popup_buttons) | Enabled (true) | Enabled (true) or Disabled   (false) | Whether buttons to open popups   for all features (with popup content) in the view should be included in a   list at the end of the view content. Disable this setting if you would prefer   to use shortcodes to place buttons inside the view content. |
| Remove   Tile Server (remove_tile_server) | Enabled (true) | Enabled (true) or Disabled   (false) | Whether the tile server should   be removed whenever at least one basemap image is in use. This will save   loading time, but may look odd if the custom basemap does not cover   sufficient area. |
| Additional   Basemap Images (basemaps) | None | List | A list of basemap images to   include. Only one field (file) is needed. It will provide a dropdown listing   all the basemaps that are in the plugin's Basemap Information list. |
| Feature   Overrides (features) | None | List | A list of features to include   and overrides for included features. |
| Max   Bounds (max_bounds.north, max_bounds.south, max_bounds.east, max_bounds.west) | None | Numbers from -180 to 180 and -90   to 90 | Defines how far the map can be   panned. All four values are required or this will be ignored. |
| Minimum   Zoom (zoom_min) | 8 | Number from 0 to 28 | Defines the furthest the map   can zoom out. |
| Maximum   Zoom (zoom_max) | 16 | Number from 0 to 28 | Defines the closest the map can   zoom in. |
| Wide   Narrative Column (wide_column) | None | Enabled (true) or Disabled   (false) |        Determines the width of the narrative column. The column will be 33% of the   page width by default. Enabling this option will change it to 40%. Note that   the narrative column will always be 100% on mobile.    |
| Show Map   Location in URL (show_map_location_in_url) | None | Enabled (true) or Disabled   (false) |        Determines whether the zoom level and center coordinates will be included in   tour URLs.    |
|     Simple Attribution List   (attribution_list) |        None |        Hidden option |        List items take a text value (required) and URL value (optional). They will   be displayed in the footer as either plain text or links. See the defaults at   the end of this section for examples. Anything provided here will be added to   the plugin list.    |
|     HTML Attribution List   (attribution_html) |        None |        Hidden option |        List items take only a text value. Use this for resources that provide HTML   code for an attribution. They will be displayed in the footer after any items   in the simple attribution list. Anything provided here will be added to the   plugin list.    |
|            Select a Tile Server (tile_server_select) | None |        Dropdown featuring list of pre-defined tile servers |        Determines which, if any, of the provided tile servers will be used.   Currently only Stamen maps (watercolor, toner, and terrain) are included.    |
|     Tile Server URL   (tile_server.url) | None |        Text |        If no tile server is selected from the dropdown, this defines the URL that   will be used to pull tiles from.    |
|     Tile Server Attribution   (tile_server.attribution_text) | None |        Text |        Identifies the tile server provided in the Tile Server URL and adds it to the   footer attribution list. Will be ignored if a tile server is selected from   the dropdown.    |
|     Tile Server Attribution   URL (tile_server.attribution_url) | None |        Text |        If provided, the attribution text above will become a link to this URL. Will   be ignored if no text is provided.    |

Notes:
- The starting position defines the initial/default view shown in the map. By default, the starting bounds will be determined by all included features. You can override this by providing the bounds manually in the page header (using expert mode) or by choosing a center and a distance from it that will be used to calculate the bounds. You can choose a feature (points only) for the center or set the longitude and latitude manually. If you provide the bounds manually, they will override any central location and distance provided.

The following options override plugin or dataset settings:
- Wide Narrative Column
- Show Map Location in URL
- Tile Server
- Legend description and alt text
- Icon Alt Text
- Icon and SVG options
- Remove Default Popup

The following options can be overriden by individual view settings:
- Only Show Current View Features
- List View Popup Buttons
- Remove Tile Server

#### Tour Data

- Only a few icon/SVG override options are provided specifically in the admin panel, but you can edit all options in expert mode.
- If a legend description is provided here, the legend alt text will defalut to this description, even if legend alt text has been set on the dataset's configuration page.

|            Option |        Default |        Values |        Description    |
|---|---|---|---|
| Select   the Dataset (file) | None | Dropdown featuring all datasets   available | Adds the selected dataset to   the tour. |
| Show All   Features (show_all) | Enabled (true) | Enabled (true) or Disabled   (false) | Whether all features from the   dataset will be added to the tour, or only a few specific ones. If disabled,   only features added to the Feature Overrides list will be included in the   tour. This is mostly useful only if you want to include a set of "features"   to select for tour/view centers without actually including the features in   the tour itself. |
| Description   for Legend (legend_text) | None | Text | A short description of the   dataset that will be included in the legend. |
| Legend   Alt Text (legend_alt) | None | Text | Ideally the legend description   will be quite short, but if it is not, a shorter description can be provided   here to be included in the alt text for each feature on the map. If this is   empty, will default to the legend description. |
| Icon Alt   Text (icon_alt) | None | Text | A brief description of the icon   image itself. This is not as important as legend text/alt text, but it can be   nice, especially if the icon has some significance to the dataset. |
| Icon   Marker (icon.file) | None | Dropdown of images from the   "user/data/leaflet-tour/images/markers" folder | File selection dropdown for   images added to the images/markers folder. If no image is selected, the   default Leaflet marker will be used. The defaults provided for the various   options may change depending on whether or not a custom icon has been chosen. |
| Icon   Width (icon.width) | None | Number (minimum of 1) | Width of the icon (in pixels) |
| Icon   Height (icon.height) | None | Number (minimum of 1) | Height of the icon (in pixels) |
| Use   Default Values (icon.use_defaults) | Disabled (false) | Enabled (true) or Disabled   (false) | Whether icon options should   default to values provided in the dataset page, if applicable, or ignore   these entirely. |
| Icon   Options | None | Hidden option | All Icon options from the   dataset page. Can be edited using expert mode. |
| SVG   Options | None | Hidden option | All SVG (and SVG Active)   options from the dataset page. Can be edited using expert mode. |

#### Feature Overrides

|            Option |        Default |        Values |        Description    |
|---|---|---|---|
| Feature   (id) | None | Dropdown of all features from   datasets included in the tour | The name of the feature to   include or modify. |
| Remove   Default Popup (remove_popup) | Disabled (false) | Enabled (true) or Disabled   (false) | Whether to ignore popup content   provided from the dataset. Does not remove popup content added to the tour   (below). |
| Popup   Content (popup_content) | None | Markdown | Content that will be displayed   in a popup for the feature. This is the same kind of markdown editor used for   normal page content. Users can open feature popups by clicking on the feature   from the map, or by clicking on a provided popup button in the tour narrative   column. |

#### Defaults

```yaml
legend: true
legend_toggles: true
only_show_view_features: false
list_popup_buttons: true
remove_tileserver: true
zoom_min: 8
zoom_max: 16
```

#### Editing YAML

- The dataset file dropdown will show dataset names in the admin panel, but will store the JSON file name in the YAML.
- The starting location dropdown will show the feature name in the admin panel, but will store the id (created by the plugin) in the YAML.
- The feature dropdown will show the feature name in the admin panel, but will store the id (created by the plugin) in the YAML.
- Image dropdowns (basmaps, icon file) should have the same value on the admin panel and in the YAML (name of the image file).
- The Tile Server selection dropdown displays the name of the tile server on the admin panel, but stores a slightly different identifier. See the list of included tile servers in the features section of this document.

### View Configuration

|            Option |        Default |        Values |        Description    |
|---|---|---|---|
| Shortcodes | None | Read Only | A list of popup button   shortcodes for features included in the view. This is for your copy and paste   convenience only. |
| Starting   - Central Location (start.location) | None | Dropdown of all Point features   from datasets included in the tour | A feature that provides the   coordinates of the starting center point. Overrides longitude and latitude   values shown below. Requires distance to form starting bounds. |
| Starting   - Center Longitude (start.long) | None | Number from -180 to 180 | Longitude (degrees) of the   starting center point. Requires latitude and distance to form starting   bounds. |
| Starting   - Center Latitude (start.lat) | None | Number from -90 to 90 | Latitude (degrees) of the   starting center point. Requires longitude and distance to form starting   bounds. |
| Distance   from Starting Center (start.distance) | None | Number (minimum 0) | A distance (degrees) that will   be used as the radius of how far out from the center point should be included   within the starting bounds. Requires longitude and latitude or location. |
| Bounds   (start.bounds.north, start.bounds.south, start.bounds.east,   start.bounds.west) | None | Hidden option | Defines the bounds on the   starting view. Overrides other start options provided. Can be edited in   expert mode. |
| Features   (features) | None | List | A list of features to include   in the view. Only one field (name/id) is needed. It will provide a dropdown   listing all the features that are included in the tour. |
| Only   Show Current View Features (only_show_view_features) | None | Enabled (true) or Disabled   (false) | Whether all features not   included in a view should be hidden when that view is entered. Ignored if the   view has no features. |
| Remove   Tile Server (remove_tile_server) | None | Enabled (true) or Disabled   (false) | Whether the tile server should   be removed whenever at least one basemap image is in use. This will save   loading time, but may look odd if the custom basemap does not cover   sufficient area. |
| Hide   Tour Basemaps (no_tour_basemaps) | Disabled (false) | Enabled (true) or Disabled   (false) | Whether to show or hide all   basemaps added to the tour, but not specifcally to this view. |
| Additional   Basemap Images (basemaps) | None | List | A list of basemap images to   include. Only one field (file) is needed. It will provide a dropdown listing   all the basemaps that are in the plugin's Basemap Information list. Any   basemaps will be added to the list of tour basemaps. |
| List   View Popup Buttons (list_popup_buttons) | None | Enabled (true) or Disabled   (false) | Whether buttons to open popups   for all features (with popup content) in the view should be included in a   list at the end of the view content. Disable this setting if you would prefer   to use shortcodes to place buttons inside the view content. |

Notes:

The view configuration page will generate a list of shortcodes for all view features when it is saved. These are useful to copy and paste into the page content to manually determine where the view popup buttons will go for each feature (just don't forget to disable list_popup_buttons). Note that all view features will be included, even if they do not actually have popup content. If a feature does not have any popup content (or the content has been removed by the tour), the button will not be added. Either way, whether it has been replaced by a button or by nothing, the shortcode will not be shown in the public-facing page content.

The starting position defines the initial bounds shown when entering the view. By default, the starting bounds will be determined by all included features. If no features have been added to the view, the tour bounds will be used instead. You can override this by providing the bounds manually in the page header (using expert mode) or by choosing a center and a distance from it that can be used to calculate the bounds. You can choose a feature (points only) for the center or set the longitude and latitude manually. If you provide the bounds manually, they will override any central location and distance provided.

The following options override tour settings:
- Only Show Current View Features
- Remove Tile Server
- List View Popup Buttons

<!-- TODO: Move to add views section -->
Adding features to the tour has several potential uses:

- If at least one feature is included, the view bounds can automatically be calculated.
- If only_show_view_features is enabled, these are the features that will be displayed.
- If you are not using the popup button shortcodes, the view will include a list of view popup buttons after the main view content. Only features included in this list (and that also have popup content, of course) will be included.

#### Defaults

```yaml
no_tour_basemaps: false
```

#### Editing YAML

- The starting location dropdown will show the feature name in the admin panel, but will store the id (created by the plugin) in the YAML.
- The feature dropdown will show the feature name in the admin panel, but will store the id (created by the plugin) in the YAML.
- The basemaps dropdown should have the same value on the admin panel and in the YAML (name of the image file).


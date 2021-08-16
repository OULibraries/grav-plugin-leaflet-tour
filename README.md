# Leaflet Tour Plugin

<!-- TODO: Plugin screenshot -->

**Leaflet Tour** is a Grav plugin designed to enable students, researchers, and anyone else interested to tell stories using a combination of narrative text and accessible interactive maps.

<!-- TODO: demo website -->

## Requirements

- Grav version 1.7.0 or higher
- PHP version 7.4 or higher
- Grav Theme Basic (enabled)
- Shortcode Core plugin
- Admin Panel plugin
<!-- TODO: grav theme basic info -->

The theme has additional plugins listed that, while not required, are strongly recommended. Please check out the documentation for the theme to see these plugins as well as important information for configuring them.

<!-- This plugin will mention other important requirements further on. Give reference to section to read thoroughly, since people probably won't read the whole document thoroughly -->

## Installation

Installing the Leaflet Tour plugin can be done in one of two ways. The GPM (Grav Package Manager) installation method enabled you to quickly and easily install the plugin with a terminal command, while the manual method enables you to do so via a zip file.

If you are using the Admin plugin (recommended) then you can use the GPM method without having to use a terminal command. Instead, you can use the Admin panel interface to look up and add the plugin.

### GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](https://learn.getgrav.org/cli-console/grav-cli-gpm) through your system's Terminal (also called the command line). From the root of your Grav install type:

```
bin/gpm install leaflet-tour
```

This will install the Leaflet Tour plugin into your `/user/plugins` directory within Grav. Its files can be found undert `/your-site/grav/user/plugins/leaflet-tour`.

Note that with the Admin panel you will instead go to the Plugins tab, click the add button, look up this plugin, and click install.

### Manual Installation

To install this plugin, download the zipfile from the plugin repository and unzip it under `/your-site/grav/user/plugins`. Then, rename the folder to `leaflet-tour`.

<!-- TODO: Make plugin repository a link to the repo. Also mention: You can also find the plugin files via GetGrav.org(https://getgrav.org/downloads/plugins). -->

The filepath to the plugin should now be: `/your-site/grav/user/plugins/leaflet-tour`

## Updating

As development for the Leaflet Tour plugin continues, new versions may become available the add additional features and functionality, improve compatibility with newer Grav releases, and generally provide a better user experience. As with installation, updating Leaflet Tour can be done through Grav's GPM system (via terminal or the Admin panel) or manually.

Please note: Any changes you have made to any of the files in the plugin will be overwritten. Any files located elsewhere (for example, a .yaml settings files placed in `user/config/plugins`) will remain intact.

### GPM Update (Preferred)

The simplest way to update this theme is via the Grav Package Manager (GPM). You can do this by navigating to the root directory of your Grav install using your system's Terminal (also called the command line) and typing the following:

```
bin/gpm update leaflet-tour
```

This command will check your Grav install to see if the Leaflet Tour plugin is due for an update. If a newer release is found, you will be asked whether or not you wish to update. To continue, type `y` and hit enter. The plugin will automatically update and clear Grav's cache.

If you are using the Admin panel, you will instead see an option to update plugins on your plugins dashboard.

### Manual Update

To manually update Leaflet Tour:

- Delete the `/your-site/user/plugins/leaflet-tour` directory. Data you have uploaded is stored in your `user/data` directory, and therefore will be preserved.
- Download the new version of the Leaflet Tour theme from either GitHub or [GetGrav.org](https://getgrav.org/downloads/themes).
- Unzip the zip file in `your-site/user/plugins` and rename the resulting folder to `leaflet-tour`.
- Clear the Grav cache. The simplest way to do this is by going to the root Grav directory in the terminal and typing `bin/grav clear-cache`. If you are using the admin panel, there is also a button to clear the cache in the navigation sidebar.

<!-- TODO: Make GitHub link to repo -->

## Setup and Quick Start

Make sure the plugin is enabled in the configuration

<!-- TODO: See how other plugins mention this in their docs -->

### Regarding Coordinates

Please note that as a matter of standards, all coordinates will be provided as \[longitude, latitude\]. This is how GeoJSON stores coordinates, since longitude corresponds to the x-value and latitude corresponds to the y-value (and x always comes before y in \[x, y\] pairs). Therefore, for consistency, the plugin will also use this convention when providing options to set coordinates.

Please also note that coordinates are provided using degrees. That is, longitude has a range from -180 to 180, and latitude has a range from -90 to 90. If you have coordinates in some other form, you can find tools to easily convert them using the internet.

### Dataset Upload and Configuration

You will need one or more datasets with geographic information. The plugin currently accepts .js files generated by qgis2web or .json files that follow the GeoJSON format. Technically, any .js file will be accepted if it is in the form of `var json_varNameHere = { "GeoJSON" }` where GeoJSON is actually just valid GeoJSON data. If you have a different type of file, you may be able to find an application or tool that can convert it to GeoJSON.

Upload your dataset(s) using the plugin configuration page. Make sure the files have fully uploaded (a checkmark should appear by the file) before saving the page. Assuming the files are valid, the plugin will create .json files for its own use, add an id to each feature, and create a dataset page where you can customize dataset options. All dataset pages will be created in the `datasets` folder, which you can find on the Pages tab of the admin panel. Open up the page for an individual dataset and use the settings to customize dataset name, feature names, legend info, and icon/svg options. Note that legend info and icon settings (and in the future svg options, too) can be overriden by an individual tour if you wish.

Any features that are points will be displayed using icon images. The plugin includes the default Leaflet marker, but you can upload icon images in the plugin config, which you can then choose in the dataset configuration page. Refer to the configuration sections in this document for more detailed information on what icon and svg/path options are provided and how to use them.

### Tile Servers and Basemap Images

Every tour will use a tile server as its default basemap. You can set the tile server for all tours using the plugin config page, but you can also override this setting for individual tours using the tour config. A dropdown has been provided to select a tile server if you want to use one of the provided options. Currently these are limited to Stamen maps, but more may be added in the future. If you wish to provide a URL to a tile server instead, make sure the dropdown has been set to `None` and add the URL and attribution data to the config.

You can also add images as additional custom basemaps. These images must be uploaded using the plugin config. After they have uploaded (make sure each image has a checkmark), you can save the page. You must save the page before the images will show up in the dropdown. You will need to add each image to the Basemap Information list in order to be able to use it. In this list you will select the image using a dropdown (if you don't see the image, make sure you remembered to save after uploading it) and set the bounds (geographic coordinates) that will define where the image is displayed. You can also set a min and max zoom and attribution data. Refer to the plugin configuration section for more information about these settings.

Once you have added a basemap to the Basemap Information list, you can then add that basemap to a tour or view using that page's config.

### Attribution

By default, each tour footer will include an attribution list for all resources used. You can remove this list using the plugin configuration if you would prefer to add the attributions yourself.

The plugin config contains an attribution list that starts with QGIS, qgis2web, and Leaflet. Leaflet is included because it is required for the map to run, and qgis2web is included because it was integral in setting up the plugin. You will probably want to remove QGIS if you did not actually use the application to create your map. You can also add any resources that you used. Please note that tile servers and basemaps include their own attribution options, so if you make use of those options you will not want to add that data to the plugin attribution list. If you have selected a tile server from the dropdown rather than providing a URL, the attribution data will automatically be added where appropriate by the plugin.

The attribution list should be used for attributions with fairly short text. A longer attribution option will be provided for any resources that provide HTML code for an attribution. (Currently this is only used by Stamen maps, if you want to see what it will look like).

### Setting up a Tour

Create a tour from the admin panel by clicking the Add button or the Add dropdown and choosing Add Page. You most likely want to have the parent page as `<root>` (i.e. `/`). The page template will need to be Tour.

The page title, which can be changed from the Content tab, will be displayed as a heading level one on the tour webpage. Any information you add to the content box will be displayed after and can be used as an introduction to the tour.

See the tour configuration section in this document for a detailed description of the various tour settings and what they do.

### Adding Views

Views are modular components that will be located inside tours. To create a view, click the Add dropdown and choose Add Module. Make sure that Page (essentially parent page) is set to the tour you want the view to be part of. Make sure the Module Template is View (it may be the only option available).

The page title, which can be changed from the Content tab, will be displayed as a heading level two on the tour webpage. Any information you add to the content box will be displayed after. You will only want to use heading levels 3-6 in this scontent. As with tours, you can add media once you have saved the page.

Refer to the configuration options section for detailed information about setting the view options.

#### Shortcodes

The view configuration Content tab includes a list of shortcodes. These will be updated whenever the configuration is saved to provide the shortcode needed for any features added to the view features list. You can copy and paste these codes into the content in order to manually determine where button to open that particular feature's popup content will be located. This allows you to put these buttons in sensible places where you actually mention the feature in the view content, rather than as part of a list at the very end of the view content. Make sure to turn off the option to list view popups (either tour config or view config) if you are making use of these shortcodes!

#### Folder Numeric Prefix

This will allow you to manually order your views from any view configuration page.

1. Go to the Advanced tab.
2. Under Ordering, toggle Folder Numeric Prefix to Enabled.
3. Save the page.

## Accessibility

<!-- TODO -->

- features
    - what does the site do automatically for accessibility?
    - definitely mention some of what is going on with the map
- configuration
    - you can definitely mess up accessibility with bad configuration - what do users need to know?
- content
    - obviously all the general accessibility standards apply
    - is there anything creators need to know about making accessible content with this particular plugin?

## Configuration

At present, you can upload either .js files generated by qgis2web of .json files following the standard GeoJSON format. If you want to upload .js files, you will have to change your security settings to allow this (.json files should not cause any problems):

1. Go to Configuration from the Admin panel.
2. Choose the Security tab (between Media and Info).
3. Find Dangerous Extensions under Uploads Security. This should have a list with several extensions, including `js`.
4. Click on `js` and press delete.
5. Save.

Configuration instructions and recommendations for other highly encouraged plugins are included in the theme readme. Please check this out, as these plugins allow associating an email with the site so you can reset a forgotten password and backing data up with git, among other things.

Below are the options for the various configuration pages. These will start by showing the yaml code, which you will see if you either edit the file directly or if you switch to expert mode in the Admin panel. In general, staying on normal mode is recommended, however, as this will provide a useful interface for modifying these settings. After the yaml code, the options will be explained in more detail, using both the yaml name (typically in parantheses) and the label associated with that option in the Admin panel interface.

### Plugin Configuration

If editing this manually, make sure to copy the `user/plugins/leaflet-tour/leaflet-tour.yaml` file to the `user/config/plugins/` folder and modify the copy. If you modify the original plugin file directly, any changes will be lost with future updates.

```yaml
enabled: true                   # Enable the theme
leaflet_tour: true              # Hidden field. Do not modify or remove!
data_files:                     # Dataset file upload (multiple) to the "data/leaflet-tour/datasets/uploads/" folder.
data_update: false              # Turn this to true to have the dataset information rebuilt from each of the uploaded files.
marker_icon_files:              # Image file upload (multiple) to the "data/leaflet-tour/images/markers/" folder.
marker_shadow_files:            # Image file upload (multiple) to the "data/leaflet-tour/images/markerShadows/" folder.
wide_column: false              # Toggle, widens the narrative column from 33% to 40% of screen width
show_map_location_in_url: true      # Toggle, include zoom level and center coordinates in tour URLs.
attribution_toggle: true        # Toggle, include list of resources used (leaflet, basemaps, etc.)
attribution_list:               # List of additional attributions to add (starts with QGIS, Leaflet, and qgis2web by default)
    # text - Identifies the resource in the attribution
    # url - If provided, the above text will link to this URL
attribution_long:               # List of additional attributions to add - HTML code
    # text - the HTML code
tileserver:
    select:                     # Dropdown menu for default available tile servers
    url:                        # If nothing is selected, manually provide a link to the desired tile server
    attribution_text:           # Text to identify the tile server in the attribution
    attribution_url:            # If provided, the above text will link to this URL
basemap_files:                  # Image file upload (multiple) to the "data/leaflet-tour/images/basemaps/" folder
basemaps:                       # List of essential basemap info
    # file - Image selection from the "data/leaflet-tour/images/basemaps/" folder
    # bounds
        # north - Latitude for north/top of map
        # south - Latitude for south/bottom of map
        # east - Longitude for east/right of map
        # west - Longitude for west/left of map
    # zoom_max - Basemap will not be zoomed in past this level
    # zoom_min - Basemap will not be zoomed out past this level
    # attribution_text - Text to identify the basemap in the attribution
    # attribution_url - If provided, the above text will link to this URL
```

#### General Tab

Plugin status (enabled): Determines whether the plugin can be used. Default is Enabled (true).

Data Files (data_files): Multiple file upload to the `user/data/leaflet-tour/basemaps/` folder.
- Files can be JavaScript (.js) files generated by qgis2web. These files consist of a variable that stores the GeoJSON data. The variable name must begin with `json_` and use only letters, numbers, and underscores. No additional content should be included (although comments at the beginning of the file should be fine).
- Files can be JSON (.json) files, so long as they are valid GeoJSON files. If you have geographic data in some other format, you may be able to find tools to easily convert the data into GeoJSON. Note that each point is written as longitude first, then latitude.
- When the configuration is saved (from the admin panel) and any new datasets have been uploaded, a .json file will be generated for each, as well as a special dataset page. The dataset page will not be visible publically, but you can use it in the admin panel to interact with the dataset and set various options. These options will be discussed in further detail in the Dataset Configuration section.

Update Datasets (data_update): When the configuration is saved (from the admin panel) with this option enabled, all the datasets in the Data Files list will have their corresponding .json file regenerated. While not all custom settings may be lost, some will be, so use this option with care (and always make a backup first).

Markers (marker_icon_files): Multiple image upload to the `user/data/leaflet-tour/images/markers/` folder.
- Images in this folder can be selected for use as marker icons for datasets.
- Currently should accept any image type, although only .png has been tested.

Marker Shadows (marker_shadow_files): Multiple image upload to the `user/data/leaflet-tour/images/markerShadows/` folder.
- Images in this folder can be selected for use as marker icon shadows for datasets.
- Currently should accept any image type, although only .png has been tested.
- Optional: You can upload and use marker icons without any need for marker shadows.

##### Advanced

Wide Narrative Column (wide_column): Toggles the narrative width from 33% to 40% of the total view width. This has no effect on mobile, since at low enough screen width the narrative and map will both take the full screen.
- Can also be set individually per tour.

Show Map Location in URL (show_map_location_in_url): Adds the zoom level and center coordinates of the map location to any tour URLs.
- Can also be set individually per tour.

Include Attribution in Footer (attribution_toggle): Toggles the list of resources used in the footer. Uncheck this option if you prefer to manually add attribution for leaflet, basemaps, etc.

Additional Resources (attribution_list): This is a list of resources to add to the attribution list included in the footer. If attribution_toggle is disabled, this list will be ignored. Resources added here will be added to attributions for every tour. You can also add resources specific to an individual tour using the tour configuration.
- Attribution Text (.text): Required, identifies the resource used.
- Attribution URL (.url): If provided, the attribution text will become a link to this URL. The URL will be ignored if no attribution text is provided.

Extra Attribution (attribution_long): This list functions similarly to the Additional Resources list, but allows you to add the full HTML code for an attribution. This is useful for any resources that provide a ready-made attribution, as all you have to do is copy and paste it.

#### Basemaps Tab

##### Tile Server

The tile server is the default basemap of the Leaflet tour. Tile servers provide a complete map that is separated into sections (tiles). Thus, instead of having to load a detailed image for the whole area, only the sections needed at a given moment are loaded. Additionally, the level of detail changes as you zoom in and out, further improving performance without reducing quality.

Select a Tile Server (tileserver.select): Dropdown menu to select a tile server. Currently only Stamen maps have been added, but others could be configured and included.

Tile Server URL (tileserver.url): Only relevant if tileserver.select is null or none. Directly provide a URL to the tile server.

Tile Server Attribution (tileserver.attribution_text): Identifies the resource used in the attribution list.
- This only applies if tileserver.url is set. The tile server dropdown will automtically add attribution.

Tile Server Attribution URL (tileserver.attribution_url): If provided, the attribution text will become a link to this URL.
- This only applies if tileserver.url is set. The tile server dropdown will automtically add attribution.
- The URL will be ignored if no attribution text is provided.

##### Custom Basemaps

Custom Basemap Images (basemap_files): Multiple image upload to the `user/data/leaflet-tour/images/basemaps/` folder.
- Images in this folder can be selected for use as additional basemaps in the tour and/or view configurations, but only if the basemap information section below has been filled out.
- These images can take a lot longer to load. It is strongly recommended to rely first and foremost on a tile server.
- Currently should accept any image type, although only .png and .jpg have been tested.
- Save the configuration after adding files and before trying to add basemap information.

Basemap Information (basemaps): A list of necessary information about each custom basemap
- Select the Basemap Image (.file): File selection dropdown for images added to the basemaps folder.
- Bounds
    - North (.bounds.north): Latitude for the Northern edge of the basemap image. (This will typically be the top of the map.)
    - South (.bounds.south): Latitude for the Southern edge of the basemap image. (This will typically be the bottom of the map.)
    - East (.bounds.east): Longitude for the Eastern edge of the basemap image. (This will typically be the right side of the map.)
    - West (.bounds.west): Longitude for the Western edge of the basemap image. (This will typically be the left side of the map.)
- Max Zoom (.zoom_max): Zoom level must be this number or below for the basemap to be displayed. Zooming in further will remove the basemap. This is useful to prevent a basemap being zoomed in too far and becoming blurry.
- Min Zoom (.zoom_min): Zoom level must be this number or above for the basemap to show. Zooming out further will remove the basemap. This is useful for hiding basemaps when they become too small to be useful.
- Attribution (.attribution_text): Identifies the resource used in the attribution list.
- Attribution URL (.attribution_url): If provided, the attribution text will become a link to this URL. The URL will be ignored if no attribution text is provided.

### Dataset Configuration

The dataset page will be added automatically when a new dataset is successfully uploaded. You can then use this page through the Admin panel to mdofiy various dataset settings. Some of the options listed here are readonly. Please do not enter expert mode or open the file directly to modify them, as they really should not be modified.

```yaml
routable: 0         # Hidden field, do not change. Ensures that the page cannot be reached publicly through the internet.
visible: 0          # Hidden field, do not change. Ensures that the page will not show up on the navigation menu of the site.
dataset_file:       # Read only field, do not change. Provides the name of the dataset this page is for.
title:              # The name of the dataset (and the title of the page)
folder:             # The name of the folder holding the dataset page.
legend_text:        # Short description of the dataset that can be included in a legend.
legend_alt:         # A shorter (unless legend_text is already really short) description of the dataset that can be included as alt text for each feature.
name_prop:          # The property in the data file that contains features' names.
features:           # List containing all valid features found in the dataset
    # id - Hidden field, do not change. The id of the feature.
    # name - Read only field, do not change. The name of the feature (either default, or the current custom name)
    # custom_name - Name to use for the feature. Is used instead of the default name, but does not overwrite it.
    # popup_content - Markdown editor for popup content.
icon_alt:           # A brief description of the icon itself (assuming one is used).
icon:               # List of icon options.
    file:           # Image selection for files added to the images/markers folder. If not set, the defaualt Leaflet marker will be used.
    width:          # Icon width (pixels)
    height:         # Icon height (pixels)
    anchor_x:       # Moves icon left
    anchor_y:       # Moves icon up
    tooltip_anchor_x:   # Moves tooltip right
    tooltip_anchor_y:   # Moves tooltip down
    shadow:         # Image selection for files added to the images/shadowMarkers folder.
    shadow_width:   # Icon shadow width (pixels)
    shadow_height:  # Icon shadow height (pixels)
    shadow_anchor_x:
    shadow_anchor_y:
    class:          # Class name that can be used to select the icon when adding custom CSS
    retina:         # Image selection for files added to the images/markers folder. Optional. Used for Retina screen devices.
svg:                # List of svg options - used for polygons etc. that need more than a simple icon
    stroke: true    # Toggle, whether or not to include a stroke (e.g. borders on polygons)
    color: "#3388ff"    # Color of the stroke, accepts hex codes
    weight: 3       # Stroke width (pixels)
    opacity: 1      # Stroke opacity
    fill: true      # Toggle, whether or not to fill the path area with color
    fillColor:      # Defaults to stroke color
    fillOpacity: 0.2
svg_active:         # Same list of svg options, but only to be applied on hover/focus
    stroke:
    color:
    weight:
    opacity:
    fill:
    fillColor:
    fillOpacity:
```

#### Options Tab

There's not a lot you would need/want to modify on this page beyond the legend info.

Dataset File (dataset_file): Read only field indicating which dataset file is associated with this page. Do not change this! It is both for your convenience/information as well as for the plugin itself so it can access the correct data.

Dataset Name (title): The name of the dataset will always be the same as the title of the page. You can rename this as you like.

Folder Name (folder): The name of the folder the page is situated in.

Description for Legend (legend_text): Short description of the dataset that can be included in a legend.
- This is required for the dataset to be shown in the legend, assuming legend is enabled for the tour.

Legend Alt Text (legend_alt): A shorter (unless legend_text is already really short) description of the dataset that can be included as alt text for each feature.
- The alt text for a given icon will read as: "Feature Name, legend alt text"
- If the feature has a popup, the alt text will end with "open popup"

#### Features Tab

Name Property (name_prop): The property in the data file that contains the features' names.
- On the initial upload, this will be automatically generated. If any property is called "name" it will be selected. Otherwise, the first property that either starts or ends with "name" will be selected. If nothing fits these parameters, the first property will be chosen.
- Make sure every feature has a value for this property! A feature without a name will be considered an invalid feature.
- If you don't like a feature's name you can give it a custom name. The custom name will not overwrite the value of the name property.

##### Features List

This list (features) is automatically generated when the page is created. It includes every (valid) feature from the dataset. Technically, you can add features, but these features will not be saved. You can also remove features, but again, the features will not actually be removed when you save.

Name (.name): Read only value. It will show either the value of the given feature's name property or the feature's custom name.

Custom Name (.custom_name): Allows you to change the name of the feature without having to actually modify the data.

Popup Content (.popup_content): A markdown editor. If left blank, no popup will be generated for the feature.
- Current content may not show up until you click inside the text area.
- You can add images to the dataset page and then use those images in the popup content (I hope).

#### Icon Tab

This tab is for point features, as they will be displayed with icon images.

Icon Alt Text (icon_alt): A brief description of the icon itself (assuming one is used).
- This is not as important as the legend alt text from the options tab, but it can be nice, especially if the icon has some significance to the tour.

##### Map Markers

The following fields come from [Leaflet's Icon Options](https://leafletjs.com/reference-1.7.1.html#icon-option).

Icon Image File (icon.file): File selection dropdown for images added to the images/markers folder. If no image is selected, the default Leaflet marker will be used. The defaults for the settings below change slightly depending on whether the default Leaflet marker is used or a custom icon is chosen.

Some of the options that come in pairs (e.g. icon anchor x and icon anchor y) may require both options to be set in order to be used. In these cases, please note that if you are overriding any settings in an individual tour, that as long as both options are provided when you combine both the options set here (in the dataset config) and the options set in the tour config, this condition will be satisfied. In the list below, any options that have this requirement will specify that.

Icon Width and Height (icon.width, icon.height): The width and height of the image in pixels.
- For custom icons, the default is 14.
- For the Leaflet default icon, the default is 25px width and 41px height.

Icon Anchor (icon.anchor_x, icon.anchor_y): The coordinates of the "tip" of the icon (relative to its top left corner). The icon will be aligned so that this point is at the marker's geographical location. Centered by default.
- For custom icons, the plugin does not supply a default. Therefore, both the x and y values must be provided. If only one is provided, it will be ignored.
- For the Leaflet default icon, the default is \[12, 41\].
- The x value moves the icon to the left, and the y value moves the icon up.

Icon Tooltip Anchor (icon.tooltip_anchor_x, icon.tooltip_anchor_y): The coordinates of the point from which tooltips will "open", relative to the icon anchor.
- For custom icons, the default is \[7, 0\]. If changing the icon width, it is recommended to adjust the tooltip x value to be half the width.
- For the Leaflet default icon, the default is \[-12, 20\].
- The x value moves the tooltip to the right, and the y value moves the tooltip down.

Shadow Image File (icon.shadow): File selection dropdown for images added to the images/markerShadows folder.
- Optional. You can add and use a custom icon without a shadow.
- For custom icons, the default is no shadow.
- For the Leaflet default icon, the default is the Leaflet default shadow.

Shadow Icon Width and Height (icon.shadow_width, icon.shadow_height): The width and height of the shadow in pixels.
- No effect if there is no shadow.
- For custom icons (if a shadow is provided), the default is the icon size.
- For the Leaflet default icon, the default is \[41, 41\].

Shadow Anchor (icon.shadow_anchor_x, icon.shadow_anchor_y): The coordinates of the "tip" of the shadow (relative to its top left corner).
- Defaults to the values of the icon anchor.
- Both the x and y values must be provided for these settings to be used. If only one is provided, it will be ignored.

Class Name (icon.class): Class name that can be used to select the icon when adding custom CSS. It will be assigned to both icon and shadow images.

Retina Icon Image (icon.retina): File selection dropdown for images added to the images/markers folder. Optional. Used for Retina screen devices.

#### SVG Tab

Non-point features require something more complex than a simple image. These will be displayed using an SVG that allows drawing lines and filling in shapes. The fields come from [Leaflet's Path Options](https://leafletjs.com/reference-1.7.1.html#path-option).

There are four additional options that are not included in the admin configuration. These are lineCap, lineJoin, dashArray, and dashOffset. You can enter expert mode to add these if you would like to customize them. Check out the Leaflet documentation (link above) for information about the default values and what the options do.

##### SVG Style

Draw Stroke (svg.stroke): Whether to draw stroke along the path. Set it to false to disable borders on polygons or circles. Defaults to true.

Stroke Color (svg.color): You can use the color picker or provide a hex color code. (e.g. "#3388ff" for Leaflet's default blue)

Stroke Width (svg.weight): The width of the stroke/line in pixels. Defaults to 3.

Stroke Opacity (svg.opacity): A number from 0 (completely transparent) to 1 (completely opaque). Defaults to 1.

Use Fill (svg.fill): Whether to fill the path with color. Set it to false to disable filling on polygons or circles. Defaults to true.

Fill Color (svg.fillColor): Defaults to the stroke color.

Fill Opacity (svg.fillOpacity): A number from 0 (completely transparent) to 1 (completely opaque). Defaults to 0.2.

##### SVG Active Style

The options here are exactly the same as the options above, except that they all start with `svg_active` instead of `svg`. These options are toggleable, which means that when toggled off they will have absolutely no value. When the feature becomes active (on focus or hover) any styles set here will be applied and override the normal styles. This helps make it visually obvious which feature a person is looking at.

When the dataset is initially created, two defaults are set:
- width (svg_active.weight) is set to 5
- fill opacity (svg_active.fillOpacity) is set to 0.4

### Tour Configuration

```yaml
datasets:                       # List of all datasets added to the tour
    # file - The dataset to add
    # show_all - Whether to include all features in the dataset, or only features specifically added to the tour features list
    # legend_text - Dataset override. Short description of the dataset that can be included in a legend.
    # legend_alt - Dataset override. A shorter (unless legend_text is already really short) description of the dataset that can be included as alt text for each feature.
    # icon_alt - Dataset override. A brief description of the icon itself (assuming one is used).
    # icon
        # file - Dataset override. Image selection for files added to the images/markers folder.
        # width - Dataset override. Width of the image in pixels.
        # height - Dataset override. Height of the image in pixels.
        # use_defaults - If enabled, any values not provided in the tour will be provided by the Leaflet/plugin defaults, rather than by the values set in the dataset page.
        # other options (hidden) - Dataset override. All other icon options listed in the dataset configuration are available to use, though they are not included in the admin panel interface.
start:
    location:                   # The id of a feature (must be in one of the datasets included in the tour) that will provide center latitude and longitude.
    long:                       # Longitude of the starting center point.
    lat:                        # Latitude of the starting center point.
    distance:                   # Distance from the provided center point that will be used to determine the bounds of the initial view.
    bounds:                     # Hidden - defines the bounds of the starting view ()
        north:
        south:
        east:
        west:
legend: true                    # Toggle, whether or not to include a legend.
legend_toggles: false           # Adds a checkbox for each dataset in the legend that can be used to show/hide all of that dataset's features on the map.
only_show_view_features: false  # Toggle, when in a specific view, only the features associated with that view will be shown on the map.
list_popup_buttons: true        # Toggle, buttons to go to all view feature popups will be added at the end of each view.
remove_tileserver: true         # Toggle, remove the tile server when a custom basemap is currently in use.
basemaps:                       # List of basemaps to include
    # file - The filename (not the full path) of the basemap to include.
features:                       # List
    # id - The id (generated by the plugin) of the feature to include
    # remove_popup - Toggle, removes any popup content that was added in the dataset configuration.
    # popup_content - Markdown editor for popup content. If popup content was also added in the dataset configuration, this content will replace that.
max_bounds:                      # Defines how far the map can be panned
    north:                      # Latitude for the Northern (top) boundary of the map.
    south:                      # Latitude for the Southern (bottom) boundary of the map.
    east:                       # Longitude for the Eastern (right) boundary of the map.
    west:                       # Longitude for the Western (left) boundary of the map.Longitude for the Western (left) boundary of the map.
zoom_min: 8                     # The map will not zoom out further than this level.
zoom_max: 16                    # The map will not zoom in further than this level.
wide_column:                    # Toggle, widens the narrative column from 33% to 40% of screen width
show_map_location_in_url:       # Toggle, include zoom level and center coordinates in tour URLs.
tileserver:
    select:                     # Dropdown menu for default available tile servers
    url:                        # If nothing is selected, manually provide a link to the desired tile server
    attribution_text:           # Text to identify the tile server in the attribution
    attribution_url:            # If provided, the above text will link to this URL
```

#### Tour Data

This is a list (datasets) for selecting which datasets should be included in the tour. It provides several options to override the general settings from the dataset configuration page, which may be useful if you want to use the same dataset in multiple tours with slightly different settings.

Only a few override options are provided for the dataset icon settings, but you can add any option available in the dataset configuration manually by using expert mode.

Select the Dataset (file): Dropdown for all valid dataset files added to the plugin.
- In the admin panel, the list will show the dataset names. If you edit the header directly (e.g. by using expert mode), however, the dataset filename is required. These files can be found in the `user/data/leaflet-tour/datasets/` folder and will all end in .json.

Show All Features (show_all): Toggles including all features in the dataset in the tour.
- Defaults to true.
- If disabled, only features added directly to the features list (further down) will be included. This is mostly useful only if you want to include a set of "features" to select for tour/view centers without actually including the features in the tour itself.

Description for Legend (legend_text): Dataset override. Short description of the dataset that can be included in a legend.

Legend Alt Text (legend_alt): Dataset override. A shorter (unless legend_text is already really short) description of the dataset that can be included as alt text for each feature.
- The alt text for a given icon will read as: "Feature Name, legend alt text"
- If the feature has a popup, the alt text will end with "open popup"
- If legend_text has been provided as an override, this will default to that, rather than to the dataset's legend_alt.

Icon Alt Text (icon_alt): Dataset override. A brief description of the icon itself (assuming one is used).
- This is not as important as the legend alt text from the options tab, but it can be nice, especially if the icon has some significance to the tour.

Icon Marker (icon.file): Dataset override. File selection dropdown for images added to the images/markers folder. If no image is selected (and no image has been selected in the dataset config either), the default Leaflet marker will be used. The defaults for the settings below change slightly depending on whether the default Leaflet marker is used or a custom icon is chosen.

Icon Width and Height (icon.width, icon.height): Dataset override. The width and height of the image in pixels.
- For custom icons, the default is 14.
- For the Leaflet default icon, the default is 25px width and 41px height.

Use Default Values (icon.use_defaults): Toggles whether or not the settings provided from the dataset configuration will be used at all.
- When disabled, tour icon options will take precedence, then dataset icon options, and finally the standard defaults.
- When enabled, tour icon options will take precedence, then the standard defaults. Dataset icon options will be ignored.

#### Starting Position

The starting position defines the initial/default view shown in the map. By default, the starting bounds will be determined by all included features. You can override this by providing the bounds manually in the page header (using expert mode) or by choosing a center and a distance from it that can be used to calculate the bounds. You can choose a location for the center or set the longitude and latitude manually.

If you provide the bounds manually, they will override any central location and distance provided.

Central Location (start.location): Dropdown list with all point features included in the tour datasets.
- From the admin side, the feature name will be displayed.
- In the page yaml (e.g. what you see when you edit the header using expert mode), the feature id will be displayed. Since the id is created by the plugin, it is strongly recommended to use normal mode to make the most of the dropdown list.
- If a valid point is chosen, it will take precedence over the cetner longitude and center latitude options below.

Center Longitude (start.long): Longitude (x value) of the central location. Provided in degrees from -180 to 180.

Center Latitude (start.lat): Latitude (y value) of the central location. Provided in degrees from -90 to 90.

Distance from Starting Center (start.distance): The distance out from the central location that will be used to calculate the starting bounds. Provided in degrees. Must be a positive number.

#### General Options

Include Legend (legend): Toggles adding a legend to the map, assuming at least one of the included datasets has a legend description. Defaults to true.

Allow Toggling Datasets in Legend (legend_toggles): Adds a checkbox for each dataset in the legend that can be used to show/hide all that dataset's features on the map. This setting is meaningless without a legend. Defaults to false.

Only Show Current View Features (only_show_view_features): When in a specific view, only the features associated with that view will be shown on the map.
- Defaults to false.
- If the view has no features, this setting will be ignored.
- Individual view settings can override this.

List View Popup Buttons (list_popup_buttons): Buttons to go to all view feature popups will be added at the end of each view. Uncheck this option if you are going to manually determine where the buttons will be located using shortcodes.
- Defaults to true.
- Individual view settings can override this.

Remove Tile Server (remove_tileserver): If toggled, will remove the tile server when a custom basemap is currently in use. This will save loading time, but may look odd if the custom basemap does not cover sufficient area.
- Defaults to true.
- Individual view settings can override this.

Additional Basemap Images (basemaps): A list of basemaps to use in this tour. Only basemaps that have been added and given the necessary settings in the plugin configuration are provided as options.
- Select the basemap (file): Dropdown list of basemap options.
- Individual view settings can add basemaps just to that view, as well.
- Individual view settings can also remove all general tour basemaps for that view.

#### Feature Overrides

This is a list (features) of features/feature overrides to include in the tour. Reasons to use this:
- Can specify features to include from a given dataset even if show_all is not selected.
- Can be used to remove the popup content provided in the dataset configuration.
- Can be used to replace the popup content provided in the dataset configuration (or to add content if there is nothing to replace).

Feature (id): Dropdown list of all features included in the tour datasets.
- From the admin side, the feature name will be displayed.
- In the page yaml (e.g. what you see when you edit the header using expert mode), the feature id will be displayed. Since the id is created by the plugin, it is strongly recommended to use normal mode to make the most of the dropdown list.

Remove Default Popup (remove_popup): Will prevent default popup (as set in dataset configuration) from being displayed, even if no popup content is provided to override it.
- This setting does nothing is popup_content is provided (below), as that will automatically override the default popup.

Popup Content (popup_content): A markdown editor. If left blank, the popup content provided in the dataset config will be used. If that is also blank (or remove_popup is enabled) then no popup will be generated.
- Current content may not show up until you click inside the text area.
- You can add images to the tour page and then use those images in the popup content (I hope).

#### Advanced

Max Bounds (max_bounds): The max bounds define how far the map can be panned. All four values must be provided or the bounds will be ignored.
- North (max_bounds.north): Latitude for the Northern boundary of the map (typically the top).
- South (max_bounds.south): Latitude for the Southern boundary of the map (typically the bottom).
- East (max_bounds.east): Longitude for the Eastern boundary of the map (typically the right).
- West (max_bounds.west): Longitude for the Western boundary of the map (typically the left).

Minimum Zoom (zoom_min): The map will not zoom out further than this level. Defaults to 8.
- The minimum zoom level is 0. At this level, the world is shown as a 256px by 256px image. (Each increase in zoom level divides each 256x256 tile into four 256x256 tiles).

Maximum Zoom (zoom_max): The map will not zoom in further than this level. Defaults to 16.
- Most tile servers offer tiles up to zoom level 18. If you are adding a tile server, you may want to check what its maximum native zoom is and use that number as the maximum zoom.

Regarding min amd max zoom: Note that basemaps also have a min and max zoom. However, with basemaps, these values determine when the basemap is shown. That is, you can zoom out further than the minimum zoom of a given basemap - it just means that the basemap will disappear until you zoom back in. For the overall tour min and max zoom, however, this defines how much you can zoom in or out at all.

#### Overrides

All of the following options override plugin settings.

Wide Narrative Column (wide_column): Toggles the narrative width from 33% to 40% of the total view width. This has no effect on mobile, since at low enough screen width the narrative and map will both take the full screen.
- Toggleable

Show Map Location in URL (show_map_location_in_url): Adds the zoom level and center coordinates of the map location to any tour URLs.
- Toggleable

Select a Tile Server (tileserver.select): Dropdown menu to select a tile server. Currently only Stamen maps have been added, but others could be configured and included.

Tile Server URL (tileserver.url): Only relevant if tileserver.select is null or none. Directly provide a URL to the tile server.
- Note that this overrides all plugin settings. That is, if tileserver.select is set in the plugin settings, this will still override it.

Tile Server Attribution (tileserver.attribution_text): Identifies the resource used in the attribution list.
- This will only override plugin settings if the tour tileserver.url is set.
- If tileserver.url is set but this is not, the plugin settings will be ignored.

Tile Server Attribution URL (tileserver.attribution_url): If provided, the attribution text will become a link to this URL.
- This will only override plugin settings if the tour tileserver.url is set.
- If tileserver.url is set but this is not, the plugin settings will be ignored.
- The URL will be ignored if no attribution text is provided.

#### Hidden Options

There are a few options that are not on the configuration page that you can still customize. To do this, you can enter expert mode and modify the page header directly.

Icon Options: For each of the datasets, all icon options that are listed on the dataset configuration page are also applicable here.

Starting Bounds (start.bounds): This option allows you to define the starting bounds directly. For example:
```yaml
start:
  bounds:
    north: 20.0
    south: 16.0
    east: 20.0
    west: 16.0
```

Routable and Visible: These options are hidden but should not be changed. Routable stays at `0` to ensure that the page cannot be reached except through the admin panel. Visible stays at `0` to ensure that the page is not included in the navigation menu.

### View Configuration

```yaml
shortcodes_list:                # Read only, do not edit. This is for your copy and paste convenience only.
start:
    location:                   # The id of a feature (must be in one of the datasets included in the tour) that will provide center latitude and longitude.
    long:                       # Longitude of the starting center point.
    lat:                        # Latitude of the starting center point.
    distance:                   # Distance from the provided center point that will be used to determine the bounds of the initial view.
    bounds:                     # Hidden - defines the bounds of the starting view ()
        north:
        south:
        east:
        west:
features:                       # List of features to include in the view
    # id - feature id (note that this is generated by the plugin)
only_show_view_features:        # Toggle, when in a specific view, only the features associated with that view will be shown on the map.
remove_tileserver:              # Toggle, remove the tile server when a custom basemap is currently in use.
no_tour_basemaps: false         # Toggle, only extra basemaps added specifically to the view itself will be shown.
basemaps:                       # List of basemaps to include in the view
    # file - The filename (not the full path) of the basemap to include.
list_popup_buttons:             # Toggle, buttons to go to all view feature popups will be added at the end of each view.
```

The view configuration page will generate a list of shortcodes for all view features when it is saved. These are useful to copy and paste into the page content to manually determine where the view popup buttons will go for each feature (just don't forget to disable list_popup_buttons). Note that all view features will be included, even if they do not actually have popup content. If a feature does not have any popup content (or the content has been removed by the tour), the button will not be added. Either way, whether it has been replaced by a button or by nothing, the shortcode will not be shown in the public-facing page content.

#### Starting Position

The starting position defines the initial bounds shown when entering the view. By default, the starting bounds will be determined by all included features. If no features have been added to the view, the tour bounds will be used instead. You can override this by providing the bounds manually in the page header (using expert mode) or by choosing a center and a distance from it that can be used to calculate the bounds. You can choose a location for the center or set the longitude and latitude manually.

If you provide the bounds manually, they will override any central location and distance provided.

Central Location (start.location): Dropdown list with all point features included in the tour datasets.
- From the admin side, the feature name will be displayed.
- In the page yaml (e.g. what you see when you edit the header using expert mode), the feature id will be displayed. Since the id is created by the plugin, it is strongly recommended to use normal mode to make the most of the dropdown list.
- If a valid point is chosen, it will take precedence over the cetner longitude and center latitude options below.

Center Longitude (start.long): Longitude (x value) of the central location. Provided in degrees from -180 to 180.

Center Latitude (start.lat): Latitude (y value) of the central location. Provided in degrees from -90 to 90.

Distance from Starting Center (start.distance): The distance out from the central location that will be used to calculate the starting bounds. Provided in degrees. Must be a positive number.

#### Features

This is a list (features) of all features added to the tour. Adding features to the tour has several potential uses:

- If at least one feature is included, the view bounds can automatically be calculated.
- If only_show_view_features is enabled, these are the features that will be displayed.
- If you are not using the popup button shortcodes, the view will include a list of view popup buttons after the main view content. Only features included in this list (and that also have popup content, of course) will be included.

Feature (id): Dropdown list of all features included in the tour datasets.
- From the admin side, the feature name will be displayed.
- In the page yaml (e.g. what you see when you edit the header using expert mode), the feature id will be displayed. Since the id is created by the plugin, it is strongly recommended to use normal mode to make the most of the dropdown list.

#### Advanced

Only Show Current View Features (only_show_view_features): When in this view, only the features associated with it will be shown on the map.
- Defaults to false.
- If the view has no features, this setting will be ignored.
- Toggleable, overrides tour settings.

Remove Tile Server (remove_tileserver): If toggled, will remove the tile server when a custom basemap is currently in use. This will save loading time, but may look odd if the custom basemap does not cover sufficient area.
- Defaults to true.
- Toggleable, overrides tour settings.

Hide Tour Basemaps (no_tour_basemaps): If toggled, only basemaps added to the view configuration basemaps list will be shown. Any basemaps added to the tour will not (unless they are also added to the view).

Additional Basemap Images (basemaps): A list of basemaps to use in this view. Only basemaps that have been added and given the necessary settings in the plugin configuration are provided as options.
- Select the basemap (file): Dropdown list of basemap options.
- Adds to (or replaces, if no_tour_basemaps is enabled) the list of tour basemaps.

List View Popup Buttons (list_popup_buttons): Buttons to go to all view feature popups will be added at the end of each view. Uncheck this option if you are going to manually determine where the buttons will be located using shortcodes.
- Defaults to true.
- Toggleable, overrides tour settings.
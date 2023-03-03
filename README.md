# Leaflet Tour Plugin

<!-- TODO: Plugin screenshot -->

**Leaflet Tour** is a plugin for the [Grav](https://learn.getgrav.org) CMS (content management system) designed to help content creators tell stories with maps. It uses scrollytelling, a narrative format in which scrolling through the content causes changes on the page. In this case, a column of content is provided on one side of the page, and scrolling through the content causes changes in the map on the other side of the page (such as panning to a given location).

Note: For small screens/mobile it is impractical to display both the narrative content and map at once, so this is handled differently.

The plugin was initially developed as part of a pilot [Digital Scholarship Fellowship program](https://libraries.ou.edu/content/digital-scholarship-fellowship) offered by The University of Oklahoma Libraries in the 2020-2021 academic year. The motivation/goals focused on:

- Making it easy for content creators to build and customize pages (called "tours") without having to work directly with HTML or JavaScript.
- Providing as accessible a website as possible (including the interactive map). It can be difficult to find accessible options for website creation, especially when complex features (like maps) are involved.
- Using open source tools like QGIS, Leaflet, and Grav instead of proprietary software like ArcGIS.

<!-- Note: Different content for website vs. readme -->

Check out the [demo site](todo:link) for a demonstration of the plugin in action as well as detailed instructions and additional documentation.

<!-- Check out the other pages on this site for a [demonstration of the plugin in action](/demo) as well as detailed instructions and additional documentation.
 -->

## <span id="requirements">Requirements</span>

<!-- TODO: Include requirement for PHP version 7.4 or higher? Might be intimidating for people unfamiliar with servers/PHP... -->

- A Grav site running Grav version 1.7.0 or higher
- [Grav Theme Basic](https://github.com/TheoAcker12/grav-theme-basic) (The theme must be enabled)
- [Admin Panel plugin](https://github.com/getgrav/grav-plugin-admin) - Note: May be installed automatically when you install Grav (for example, if you use Reclaim Hosting)
- [Shortcode Core plugin](https://github.com/getgrav/grav-plugin-shortcode-core)

## <span id="installation">Installation</span>

<!-- TODO: Develop skeleton and provide instructions on setting up the skeleton -->

Installing the Leaflet Tour plugin can be done in one of two ways. The [Grav Package Manager (GPM)](https://learn.getgrav.org/cli-console/grav-cli-gpm) installation method enables you to install the plugin with the admin panel or a terminal command, while the manual method enables you to do so via a zip file.

<!-- TODO: Add to GPM and remove this statement -->
Note that the GPM method will not be available until the plugin has actually been released publicly and added to the GPM.

### <span id="gpm-installation">GPM Installation (Preferred)</span>

The simplest way to install this plugin is via the admin panel, especially since the admin plugin is a requirement. To install, go to the Plugins tab on your dashboard, click the **Add** button, look up this plugin, and then click **Install**.

Alternatively, you can install this plugin using your system's terminal or command line. From the root of your Grav directory type `bin/gpm install leaflet-tour`. This will install the Leaflet Tour plugin into your `/user/plugins` directory within Grav. Its files will be found under `your-site/grav/user/plugins/leaflet-tour`.

### <span id="manual-installation">Manual Installation</span>

To install this plugin manually:

<!-- TODO: possibly link to getgrav website -->

1. Download the zip file from the [plugin repository](https://github.com/TheoAcker12/grav-plugin-leaflet-tour) or by finding the files on the GetGrav website.
2. Upload the file to `your-site/grav/user/plugins`.
3. Unzip/extract the file.
4. Rename the folder to `leaflet-tour`.

The filepath to the plugin should now be `your-site/grav/user/plugins/leaflet-tour`.

## <span id="updating">Updating</span>

Updates to the Leaflet Tour plugin may be published in the future. As with installation, you can update the plugin through the Grav Package Manager (via the admin panel or your system's terminal) or manually.

Please note: Any changes you have made to any of the files in the plugin will be overwritten. Any files located elsewhere (for example, a .yaml settings file placed in `user/config/plugins`) will remain intact. Therefore, it is strongly discouraged to make any changes directly to plugin files.

### <span id="gpm-update">GPM Update (Preferred)</span>

The simplest way to update this plugin is via the admin panel. To do this, go to the Plugins tab on your dashboard and check for updates. The dashboard will indicate if any plugins have available updates and will allow you to update them individually or all at once.

Alternatively, you can update this plugin using your system's terminal or command line. From the root of your Grav directory type `bin/gpm update leaflet-tour`. This will check if the Leaflet Tour plugin has any updates. If it does, you will be asked whether or not you wish to update. To continue, type `y` and hit enter.

### <span id="manual-update">Manual Update</span>

To update this plugin manually:

1. Delete the `your-site/user/plugins/leaflet-tour` directory.
2. Follow the manual installation directions from this readme.
3. Clear the Grav cache by going to your root Grav directory and entering `bin/grav clear-cache` on the terminal.

Note: If you are using the admin panel, there is also a button to clear the cache in the navigation sidebar.

## <span id="usage">Usage</span>

This is a very brief overview. Check out the [Getting Started](https://theoacker.oucreate.com/leaflet-tour/getting-started) page on the demo site for usage instructions.

The standard workflow will look something like this:

1. Add datasets (either by uploading dataset files via the plugin configuration or creating new dataset pages manually).
2. Create one or more tours using those datasets.
3. Populate tours with views.

### <span id="features">Features</span>

<!-- TODO: Include this section? Anything else to add in this section? Include links to the relevant information for each listed feature? -->

- Customize your site with lots of configuration options (for theme, plugin, datasets, tours, and views).
- Add popup content to features on the map.
- Use shortcodes to insert buttons for feature popup content within tour/view content.
- Customize the map by choosing one of the many tile servers included in leaflet providers.

## <span id="credits">Credits</span>

- The original DS Fellowship team: Tara Carlisle, Theo Acker, Dr. Zenobie Garrett, Dr. John Stewart working with fellowship recipients Dr. Asa Randall and Laura Pott
- Primary developer: Theo Acker
- Science Gateways Community Institute (SGCI) for user experience consulting

The plugin uses JavaScript libraries [Leaflet](https://leafletjs.com/) and [Scrollama](https://github.com/russellgoldenberg/scrollama).

<!-- TODO: libraries used (leaflet, scrollama), other inspiration/code used? -->

<!-- TODO: Link to SGCI -->

## <span id="contributing">Contributing</span>

If you encounter any errors or bugs or would like to request a feature, please [open an issue on GitHub](https://github.com/TheoAcker12/grav-plugin-leaflet-tour/issues) or send an email to digitalscholarship@ou.edu.

<!-- TODO: pull requests -->

This plugin uses the MIT license. Feel free to modify, remix, and/or redistribute the code as long as you provide attribution to the original.
# StaticHTMLOutput

WordPress plugin to generate a static copy of your site and deploy to GitHub Pages, S3, Netlify, etc.  Increase security, pageload speed and hosting options. Connect WordPress into your CI/CD workflow.

**Note: This is the continuation of what was originally named "WP Static HTML Output Plugin". A new approach is available in [WP2Static](https://github.com/WP2Static/wp2static)


[![CircleCI](https://circleci.com/gh/WP2Static/static-html-output.svg?style=svg)](https://circleci.com/gh/WP2Static/static-html-output)


## WordPress as a Static Site Generator

Watch Leon Stafford's [talk from WordCamp Brisbane 2018](http://www.youtube.com/watch?v=HPc4JjBvkrU)

[![WordPress as a Static Site Generator](http://img.youtube.com/vi/HPc4JjBvkrU/0.jpg)](http://www.youtube.com/watch?v=HPc4JjBvkrU)


## Table of contents

* [External resources](#external-resources)
* [Opinionated software](#opinionated-software)
* [Installation](#installation)
* [WP-CLI commands](#wp-cli-commands)
* [Hooks](#hooks)
  * [Modify the initial list of URLs to crawl](#modify-the-initial-list-of-urls-to-crawl)
  * [Post-deployment hook](#post-deployment-hook)
* [Development](#development)
* [Localisation / translations](#localisation--translations)
* [Support](#support)
* [Notes](#notes)

## External resources

 - [WordPress.org plugin page](https://wordpress.org/plugins/static-html-output-plugin)
 - [Marketing site](https://statichtmloutput.com)
 - [Documentation](https://statichtmloutput.com)
 - [Forum](https://forum.statichtmloutput.com)
 - [CircleCI](https://circleci.com/gh/leonstafford/statichtmloutput) *master* [![CircleCI](https://circleci.com/gh/leonstafford/statichtmloutput/tree/master.svg?style=svg)](https://circleci.com/gh/leonstafford/statichtmloutput/tree/master) *develop* [![CircleCI](https://circleci.com/gh/leonstafford/statichtmloutput/tree/develop.svg?style=svg)](https://circleci.com/gh/leonstafford/statichtmloutput/tree/develop)

## Opinionated software

 - speed over beautiful code
 - human readable code over short variable names
 - own-code vs adding libraries
 - benchmarking over opinions (performance)
 - less clicks == better UX
 - user configurable options vs developer opinions


## WP-CLI commands

 - `wp statichtmloutput options --help`
```
NAME

  wp statichtmloutput options

DESCRIPTION

  Read / write plugin options

SYNOPSIS

  wp statichtmloutput options

OPTIONS

  <list> [--reveal-sensitive-values]

  Get all option names and values (explicitly reveal sensitive values)

  <get> <option-name>

  Get or set a specific option via name

  <set> <option-name> <value>

  Set a specific option via name


EXAMPLES

  List all options

    wp statichtmloutput options list

  List all options (revealing sensitive values)

    wp statichtmloutput options list --reveal_sensitive_values

  Get option

    wp statichtmloutput options get selected_deployment_option

  Set option

    wp statichtmloutput options set baseUrl 'https://mystaticsite.com'
```
 - `wp statichtmloutput generate`

```
Generating static copy of WordPress site
Success: Generated static site archive in 00:00:04
```

 - `wp statichtmloutput deploy --test`
 - `wp statichtmloutput deploy`
 - `wp statichtmloutput generate`

```
Generating static copy of WordPress site
Success: Generated static site archive in 00:00:04
```

 - `wp statichtmloutput deploy --test`
 - `wp statichtmloutput deploy`

```
Deploying static site via: zip
Success: Deployed to: zip in 00:00:01
Sending confirmation email...
```

## Hooks

### Modify the initial list of URLs to crawl

 - `statichtmloutput_modify_initial_crawl_list`
 - Filter hook

*signature*
```php
apply_filters(
    'statichtmloutput_modify_initial_crawl_list',
    $url_queue
);
```

*example usage*
```php
function add_additional_urls( $url_queue ) {
    $additional_urls = array(
        'http://mydomain.com/custom_link_1/',
        'http://mydomain.com/custom_link_2/',
    );

    $url_queue = array_merge(
        $url_queue,
        $additional_urls
    );

    return $url_queue;
}

add_filter( 'statichtmloutput_modify_initial_crawl_list', 'add_additional_urls' );
```
### Post-deployment hook

 - `statichtmloutput_post_deploy_trigger`
 - Action hook

*signature*
```php
do_action(
  'statichtmloutput_post_deploy_trigger',
  $archive
);
```

*example usage*
```php
function printArchiveInfo( $archive ) {
    error_log( print_r( $archive, true ) );
}

add_filter( 'statichtmloutput_post_deploy_trigger', 'printArchiveInfo' );
```

*example response*
```
Archive Object
(
    [settings] => Array
        (
            [selected_deployment_option] => github
            [baseUrl] => https://leonstafford.github.io/demo-site-wordpress-static-html-output-plugin/
            [wp_site_url] => http://example.test/
            [wp_site_path] => /srv/www/example.com/current/web/wp/
            [wp_uploads_path] => /srv/www/example.com/current/web/app/uploads
            [wp_uploads_url] => http://example.test/app/uploads
            [wp_active_theme] => /wp/wp-content/themes/twentyseventeen
            [wp_themes] => /srv/www/example.com/current/web/app/themes
            [wp_uploads] => /srv/www/example.com/current/web/app/uploads
            [wp_plugins] => /srv/www/example.com/current/web/app/plugins
            [wp_content] => /srv/www/example.com/current/web/app
            [wp_inc] => /wp-includes
            [crawl_increment] => 1
        )

    [path] => /srv/www/example.com/current/web/app/uploads/wp-static-html-output-1547668758/
    [name] => wp-static-html-output-1547668758
    [crawl_list] => 
    [export_log] => 
)

```

## Development

This repo contains the latest code, which you can clone/download to get the bleeding edge, else install via the [official WordPress Plugin page](https://wordpress.org/plugins/static-html-output-plugin/)

If you'd like to contribute, please follow the usual GitHub procedures (create an Issue, fork repo, submit PR). If you're unsure about any of that, contact me and I'll be happy to help.

In trying to make development/contributing easier, we'll keep requirements to a minimum. If you prefer Docker, Local by FlyWheel, Valet, Bedrock, Linux, BSD, Mac, they're all fine. This is a WordPress plugin, so anywhere you can run WordPress, you can do development on this :)


### Localisation / translations

Localisation within the plugin isn't supported. Rather, it's recommended to use a browser extension if you need help translating the UI or you can run our documentation pages through any translation service.

## Support

Please [raise an issue](https://github.com/WP2Static/static-html-output-plugin/issues/new) here on GitHub or on the plugin's [support forum](https://forum.statichtmloutput.com).

## Notes

When cloning the repo for direct use, clone it into a dir named after the official WP plugin's slug, `static-html-output-plugin`, this will make life easier.


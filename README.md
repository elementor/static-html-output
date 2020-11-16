# StaticHTMLOutput

[![CircleCI](https://circleci.com/gh/leonstafford/static-html-output.svg?style=svg)](https://circleci.com/gh/leonstafford/static-html-output)
[![Packagist](https://img.shields.io/packagist/v/leonstafford/static-html-output.svg?color=239922&style=popout)](https://packagist.org/packages/leonstafford/static-html-output)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-239922)](https://github.com/phpstan/phpstan)

WordPress plugin to generate a static copy of your site and deploy to GitHub Pages, S3, Netlify, etc.  Increase security, pageload speed and hosting options. Connect WordPress into your CI/CD workflow.

### Other WordPress SSGs I mantain

 - [WP2Static](https://github.com/WP2Static/wp2static)
 - [SimplerStatic](https://github.com/WP2Static/simplerstatic)

 - [Static HTML Output on wordpress.org](https://wordpress.org/plugins/static-html-output-plugin)
 - [Homepage](https://statichtmloutput.com)
 - [Documentation](https://statichtmloutput.com/docs/)
 - [Support Forum](https://www.staticword.press/c/wordpress-static-site-generators/static-html-output/7)

## WP-CLI commands

 - `wp statichtmloutput COMMAND`

Where `COMMAND` can be any of:

 - `options`
 - `generate`
 - `deploy`
 - `deploy_cache`

Get help for any command by appending `--help`

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
    $additional_urls = [
        'http://mydomain.com/custom_link_1/',
        'http://mydomain.com/custom_link_2/',
    ];

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

## Contributing / development

Contributions are very much welcome! Please don't be intimidated to file an issue, create a Pull Request or email me (Leon) [me@ljs.dev](mailto:me@ljs.dev).

### Developing

 - `git clone git@github.com:WP2Static/static-html-output-plugin.git`
 - `cd static-html-output-plugin`
 - `composer install`
 - `composer test`
 - `composer coverage` (optional coverage generation, requires [Xdebug](https://xdebug.org))

### Building an install .zip file

 - `composer build INSTALLER_FILENAME`

This will create the installer and place in your `$HOME/Downloads` directory.

On Windows, you will need the `zip` utility available to build an installer. I recommend using Git Bash shell and then manually installing the zip utility as per [these instructions](https://stackoverflow.com/a/55749636/1668057)

### Localisation / translations

Localisation within the plugin isn't supported. Rather, it's recommended to use a browser extension if you need help translating the UI or you can run our documentation pages through any translation service.

## Support

Please [raise an issue](https://github.com/WP2Static/static-html-output-plugin/issues/new) here on GitHub or on the plugin's [support forum](https://forum.wp2static.com).


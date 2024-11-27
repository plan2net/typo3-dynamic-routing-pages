# Dynamic Routing Pages

Instead of hardcoding the `limitToPages` configuration for your route enhancers this package can automatically detect
the necessary pages for you and generate the configuration on the fly.

## Problem

Imagine the following typical routing configuration for the news plugin.

````
routeEnhancers:
  NewsPages:
    type: Extbase
    # add every page-ID that contains a News Plugin
    limitToPages:
      - 23
      - 42
      - 123
      - 242
    extension: News
    plugin: Pi1
    routes:
      - { routePath: '/{myNewsTitle}', _controller: 'News::detail', _arguments: {'myNewsTitle': 'news'} }
      - { routePath: '/{myPagination}', _controller: 'News::list', _arguments: {'myPagination': '@widget_0/currentPage'} }
      - { routePath: '/{year}/{month}', _controller: 'News::list', _arguments: {'year' : 'overwriteDemand/year', 'month' : 'overwriteDemand/month'} }
    defaultController: 'News::list'
    # ...
````

Hardcoding the page ids for your plugin routes has the major drawback that you have to adapt the configuration as soon as someone creates a new page with a plugin (which might happen in a CMS).
With the route enhancers configuration not available in the Site module this means you have to ship an updated configuration file every time an editor creates a plugin page.

## Solution

````
routeEnhancers:
  NewsPages:
    type: Extbase
    dynamicPages:
        withPlugin: news_pi1
    extension: News
    plugin: Pi1
    routes:
      - { routePath: '/{myNewsTitle}', _controller: 'News::detail', _arguments: {'myNewsTitle': 'news'} }
      - { routePath: '/{myPagination}', _controller: 'News::list', _arguments: {'myPagination': '@widget_0/currentPage'} }
      - { routePath: '/{year}/{month}', _controller: 'News::list', _arguments: {'year' : 'overwriteDemand/year', 'month' : 'overwriteDemand/month'} }
    defaultController: 'News::list'
    # ...
````

Notice the `dynamicPages` configuration. This package will populate the `limitToPages` with matching pages.

## Reference

`dynamicPages` has three possible properties.

### `withCType`

Can be either:
- A string or an array of `withCType` values that will find all pages containing content elements with given CType.
- A configuration object for more specific matching:
```yaml
withCType:
  identifiers:
    - my_special_type
  flexFormRestrictions:
    - field: settings.someField
      value: '1'
```

The `flexFormRestrictions` allow you to find only content elements where specific FlexForm fields match certain values.

### `withPlugin`

Can be either:
- A string or an array of `tt_content.list_type` values that will find all pages containing at least one of the given plugins.
- A configuration object for more specific matching:
```yaml
withPlugin:
  identifiers:
    - news_pi1
  flexFormRestrictions:
    - field: settings.eventRestriction
      value: '1'
```

Multiple FlexForm restrictions can be defined and will be combined with AND logic:
```yaml
withPlugin:
  identifiers:
    - news_pi1
  flexFormRestrictions:
    - field: settings.eventRestriction
      value: '1'
    - field: settings.archiveRestriction
      value: 'active'
```

This is particularly useful when you need to distinguish between different plugin configurations on your pages. For example, you might want different routing rules for news plugins that show events versus those that show regular news items.

### `containsModule`

Can be a string or an array of `pages.module` values. Will find all pages that have "Contains Plugin" set to one of the given values.

### `withDoktypes`

Can be a string or an array of `switchableControllerActions` values. Will find all pages that contain plugins with the given action configured.

## Examples

Here are some practical examples of using FlexForm restrictions:

```yaml
routeEnhancers:
  EventNews:
    type: Extbase
    dynamicPages:
      withPlugin:
        identifiers:
          - news_pi1
        flexFormRestrictions:
          - field: settings.eventRestriction
            value: '1'
    extension: News
    plugin: Pi1
    routes:
       …
```

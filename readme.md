Bolt RSS Aggregator
===================

An RSS Aggregator extension for the [Bolt CMS](https://www.bolt.cm). Shows feed items of
external RSS feeds anywhere on your site.

Instructions
============

 1. Download the extension and place it into your app/extensions folder as
    app/extensions/RSSAggregator
 2. Place the `{{ rss_aggregator() }}` Twig function in your template. It requires at
    least 1 parameter: the feed URL. Example: `{{ rss_aggregator('http://rss.cnn.com/rss/edition.rss') }}`

You can pass several options to the Twig function:
`{{ rss_aggregator('http://rss.cnn.com/rss/edition.rss', { 'limit': limit, 'showDesc': true }) }}`

 - limit: The amount of links to be shown, default: 5
 - showDesc: Show the full description, default: false
 - showDate: Show the date, default: false
 - descCutoff: Number of characters to display in the description, default: 100
 - cacheMaxAge: The time a cached feed stays valid in minutes, default: 15, set to 0 to disable caching

If you get the error 'External feed could not be loaded!'-error, check the following:

 - Your webserver must be allowed to fetch URLs from the 'outside world'
 - The feed must be valid XML. Validate this, using the Validome XML validator:
   [http://www.validome.org/xml/](http://www.validome.org/xml/)

Customization
=============

Override the CSS styles defined in `extensions/vendor/bolt/rssaggregator/css/rssaggregator.css`
in your own stylesheet.

Support
=======

Please use the GitHub issue tracker: [GawainLynch/bolt-extension-rssaggregator](https://github.com/GawainLynch/bolt-extension-rssaggregator/issues)

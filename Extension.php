<?php

namespace Bolt\Extension\Bolt\RSSAggregator;

use Bolt\BaseExtension;

/**
 * RSS Aggregator Extension for Bolt
 *
 * @author Sebastian Klier <sebastian@sebastianklier.com>
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Extension extends BaseExtension
{
    const NAME = 'RSSAggregator';

    public function getName()
    {
        return Extension::NAME;
    }

    /**
     * Initialize RSS Aggregator
     */
    public function initialize()
    {
        $this->app->before([$this, 'before']);

        // Initialize the Twig function
        $this->addTwigFunction('rss_aggregator', 'twigRssAggregator');
    }

    /**
     * Before middleware
     */
    public function before()
    {
        if ($this->app['config']->getWhichEnd() !== 'frontend') {
            return;
        }

        // Add CSS file
        if (!empty($this->config['css'])) {
            $this->addCSS($this->config['css']);
        }
    }

    /**
     * Twig function {{ rss_aggregator() }} in RSS Aggregator extension.
     *
     * @param string $url
     * @param array  $options
     *
     * @return \Twig_Markup
     */
    public function twigRssAggregator($url = false, array $options = array())
    {
        if (!$url) {
            return new \Twig_Markup('External feed could not be loaded! No URL specified.', 'UTF-8');
        }
        $options = array_merge($this->getDefaultOptions(), $options);

        // Construct a cache handle from the URL
        $handle = preg_replace('/[^A-Za-z0-9_-]+/', '', $url);
        $handle = str_replace('httpwww', '', $handle);
        $cachedir = $this->app['resources']->getPath('cache') . '/rssaggregator/';
        $cachefile = $cachedir.'/'.$handle.'.cache';

        // Create cache directory if it does not exist
        if (!file_exists($cachedir)) {
            mkdir($cachedir, 0777, true);
        }

        // Use cache file if possible
        if (file_exists($cachefile)) {
            $now = time();
            $cachetime = filemtime($cachefile);
            if ($now - $cachetime < $options['cacheMaxAge'] * 60) {
                return new \Twig_Markup(file_get_contents($cachefile), 'UTF-8');
            }
        }

        $feed = $this->getFeed();

        $this->app['twig.loader.filesystem']->addPath(__DIR__ . '/assets/');

        $html = $this->app['render']->render('rssaggregator.twig', array(
                'items'   => $feed,
                'options' => $options,
                'config'  => $this->config
            )
        );

        // create or refresh cache file
        file_put_contents($cachefile, $html);

        return new \Twig_Markup($html, 'UTF-8');
    }

    /**
     * Load a remote feed.
     *
     * @return array
     */
    protected function getFeed()
    {
        // Make sure we are sending a user agent header with the request
        $streamOpts = array(
            'http' => array(
                'user_agent' => 'libxml',
            )
        );

        libxml_set_streams_context(stream_context_create($streamOpts));

        $doc = new \DOMDocument();

        // Load feed and suppress errors to avoid a failing external URL taking down our whole site
        if (!@$doc->load($url)) {
            return new \Twig_Markup('External feed could not be loaded!', 'UTF-8');
        }

        // Parse document
        $feed = array();

        // if limit is set higher than the actual amount of items in the feed, adjust limit
        if (is_int($this->config['limit'])) {
            $limit = $this->config['limit'];
        } else {
            $limit = 20;
        }

        $items = $doc->getElementsByTagName('item');
        $entries = $doc->getElementsByTagName('entry');

        if (!$items->length === 0) {
            foreach ($items as $node) {
                $feed[] = array(
                    'title' => $node->getElementsByTagName('title')->item(0)->nodeValue,
                    'desc'  => $node->getElementsByTagName('description')->item(0)->nodeValue,
                    'link'  => $node->getElementsByTagName('link')->item(0)->nodeValue,
                    'date'  => $node->getElementsByTagName('pubDate')->item(0)->nodeValue,
                );

                if (count($feed) >= $limit) {
                    break;
                }
            }
        } elseif (!$entries->length === 0) {
            foreach ($entries as $node) {
                $feed[] = array(
                    'title' => $node->getElementsByTagName('title')->item(0)->nodeValue,
                    'desc'  => $node->getElementsByTagName('content')->item(0)->nodeValue,
                    'link'  => $node->getElementsByTagName('link')->item(0)->getAttribute('href'),
                    'date'  => $node->getElementsByTagName('published')->item(0)->nodeValue,
                );

                if (count($feed) >= $limit) {
                    break;
                }
            }
        }

        return $feed;
    }

    /**
     * Get the default options values.
     *
     * @return array
     */
    protected function getDefaultOptions()
    {
        return array(
            'limit'              => 5,
            'showDesc'           => false,
            'showDate'           => false,
            'descCutoff'         => 100,
            'cacheMaxAge'        => 15,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultConfig()
    {
        return array(
            'css'                => false,
            'title_length'       => 100,
            'description_length' => 200,
            'date_format'        => '%a %x',
            'target_blank'       => true
        );
    }
}

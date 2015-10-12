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

        // Use cached data where applicable
        $key = 'rssaggregator-' . md5($url);
        $html = $this->app['cache']->fetch($key);
        if (!$html) {
            $options = array_merge($this->getDefaultOptions(), $options);
            $html = $this->getRender($url, $options);
        }

        return $html;
    }

    /**
     * Get a rendered feed.
     *
     * @param string $url
     * @param array  $options
     *
     * @return \Twig_Markup
     */
    protected function getRender($url, array $options)
    {
        $feed = $this->getFeed($url, $options);
        $this->app['twig.loader.filesystem']->addPath(__DIR__ . '/assets/');
        $html = $this->app['render']->render('rssaggregator.twig', array(
            'items'   => $feed,
            'options' => $options,
            'config'  => $this->config
        ));

        $html = new \Twig_Markup($html, 'UTF-8');
        $key = 'rssaggregator-' . md5($url);
        $this->app['cache']->save($key, $html, $options['cacheMaxAge'] * 60);

        return $html;
    }

    /**
     * Load a remote feed.
     *
     * @param string $url
     * @param array  $options
     *
     * @return array
     */
    protected function getFeed($url, array $options)
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
        if (is_int($options['limit'])) {
            $limit = $options['limit'];
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

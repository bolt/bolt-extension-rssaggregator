<?php

namespace Bolt\Extension\Bolt\RSSAggregator;

use Bolt\BaseExtension;
use GuzzleHttp\Exception\RequestException;

/**
 * RSS Aggregator Extension for Bolt
 *
 * @author Sebastian Klier <sebastian@sebastianklier.com>
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class RSSAggregatorExtension extends SimpleExtension
{
    const NAME = 'RSSAggregator';

    public function getName()
    {
        return RSSAggregatorExtension::NAME;
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
        if ($feed === false) {
            return new \Twig_Markup('External feed could not be loaded!', 'UTF-8');
        }

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
        $feed = array();

        try {
            $fetched = $this->app['guzzle.client']->get($url);
            $xml = $fetched->xml();
        } catch (RequestException $e) {
            return false;
        }

        // Get RSS Feeds
        if ($xml->channel->count() > 0) {
            foreach ($xml->channel->item as $node) {
                $feed[] = array(
                    'title' => $node->title,
                    'desc'  => $node->description,
                    'link'  => $node->link,
                    'date'  => $node->pubDate,
                );

                if (count($feed) >= $options['limit']) {
                    return $feed;
                }
            }
        }

        // Get ATOM Feeds
        if ($xml->entry->count() > 0) {
            foreach ($xml->entry as $node) {
                $link = $node->link->attributes();
                $feed[] = array(
                    'title' => $node->title,
                    'desc'  => $node->content,
                    'link'  => $link['href'],
                    'date'  => $node->published,
                );

                if (count($feed) >= $options['limit']) {
                    return $feed;
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

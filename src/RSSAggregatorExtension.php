<?php

namespace Bolt\Extension\Bolt\RSSAggregator;

use Bolt\Extension\SimpleExtension;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;

/**
 * RSS Aggregator Extension for Bolt
 *
 * @author Sebastian Klier <sebastian@sebastianklier.com>
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class RSSAggregatorExtension extends SimpleExtension
{
    public function __construct()
    {
        $a = 1;
    }

    /**
     * Twig function {{ rss_aggregator() }} in RSS Aggregator extension.
     *
     * @param string $url
     * @param array $options
     *
     * @return \Twig_Markup
     */
    public function twigRssAggregator($url = false, array $options = array())
    {
        if (!$url) {
            return new \Twig_Markup('External feed could not be loaded! No URL specified.', 'UTF-8');
        }

        // Use cached data where applicable
        $app = $this->getContainer();
        $key = 'rssaggregator-'.md5($url);
        $options = array_merge($this->getDefaultOptions(), $options);

        if ($options['cacheMaxAge'] > 0) {
            $html = $app['cache']->fetch($key);

            return $html;
        }

        return $this->getRender($url, $options);
    }

    /**
     * @return array
     */
    protected function registerTwigPaths()
    {
        return ['templates'];
    }

    /**
     * @return array
     */
    protected function registerTwigFunctions()
    {
        $options = ['is_safe' => ['html'], 'safe' => true];

        return [
            'rss_aggregator' => ['twigRssAggregator', $options],
        ];
    }

    /**
     * @return array|\Bolt\Asset\AssetInterface[]
     */
    protected function registerAssets()
    {
        $config = $this->getConfig();

        if (!empty($config['css'])) {
            return [
                $config['css'],
            ];
        }

        return [];
    }

    /**
     * Get a rendered feed.
     *
     * @param string $url
     * @param array $options
     *
     * @return \Twig_Markup
     */
    protected function getRender($url, array $options)
    {
        $app = $this->getContainer();
        $feed = $this->getFeed($url, $options);
        if ($feed === false) {
            return new \Twig_Markup('External feed could not be loaded!', 'UTF-8');
        }

        /*        $app['twig.loader.filesystem']->addPath(__DIR__.'/assets/');
        */
        $context = [
            'items' => $feed,
            'options' => $options,
            'config' => $config = $this->getConfig(),
        ];

        $html = $this->renderTemplate($config['template'], $context);

        $html = new \Twig_Markup($html, 'UTF-8');
        $key = 'rssaggregator-'.md5($url);
        $app['cache']->save($key, $html, $options['cacheMaxAge'] * 60);

        return $html;
    }

    /**
     * Load a remote feed.
     *
     * @param string $url
     * @param array $options
     *
     * @return array|bool
     */
    protected function getFeed($url, array $options)
    {
        $feed = array();

        try {
            $app = $this->getContainer();
            /** @var Response $fetched */
            $fetched = $app['guzzle.client']->get($url);
            $raw = (string) $fetched->getBody();
            $xml = new \SimpleXMLElement($raw);
        } catch (RequestException $e) {
            return false;
        }

        // Get RSS Feeds
        if ($xml->channel->count() > 0) {
            foreach ($xml->channel->item as $node) {
                $feed[] = array(
                    'title' => $node->title,
                    'desc' => $node->description,
                    'link' => $node->link,
                    'date' => $node->pubDate,
                    'node' => $node,
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
                    'desc' => $node->content,
                    'link' => $link['href'],
                    'date' => $node->published,
                    'node' => $node,
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
            'limit' => 5,
            'showDesc' => false,
            'showDate' => false,
            'descCutoff' => 100,
            'cacheMaxAge' => 15,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultConfig()
    {
        return array(
            'css' => false,
            'title_length' => 100,
            'description_length' => 200,
            'date_format' => '%a %x',
            'target_blank' => true
        );
    }
}

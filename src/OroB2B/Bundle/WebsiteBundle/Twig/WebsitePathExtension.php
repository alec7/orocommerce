<?php

namespace OroB2B\Bundle\WebsiteBundle\Twig;

use OroB2B\Bundle\WebsiteBundle\Resolver\WebsiteUrlResolver;

class WebsitePathExtension extends \Twig_Extension
{
    const NAME = 'orob2b_website_path';
    
    /**
     * @var WebsiteUrlResolver
     */
    protected $websiteUrlResolver;

    /**
     * @param WebsiteUrlResolver $websiteUrlResolver
     */
    public function __construct(WebsiteUrlResolver $websiteUrlResolver)
    {
        $this->websiteUrlResolver = $websiteUrlResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            'website_path' => new \Twig_SimpleFunction(
                $this->websiteUrlResolver,
                'getWebsitePath'
            ),
            'website_secure_path' => new \Twig_SimpleFunction(
                $this->websiteUrlResolver,
                'getWebsiteSecurePath'
            )
        ];
    }
}

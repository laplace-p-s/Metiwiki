<?php

namespace App\Markdown\WikiLink;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

class WikiLinkExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        // 通常のリンク（[text](url)）の `[` より先に解析させる
        $environment->addInlineParser(new WikiLinkParser, 200);
        $environment->addRenderer(WikiLink::class, new WikiLinkRenderer);
    }
}

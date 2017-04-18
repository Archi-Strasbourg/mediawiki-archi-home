<?php
/**
 * SpecialArchiHome class.
 */

namespace ArchiHome;

/**
 * SpecialPage Special:ArchiHome that displays the custom homepage.
 */
class SpecialArchiHome extends \SpecialPage
{
    /**
     * SpecialArchiHome constructor.
     */
    public function __construct()
    {
        parent::__construct('ArchiHome');
    }

    /**
     * Send a request to the MediaWiki API.
     *
     * @param array $options Request parameters
     *
     * @return array
     */
    private function apiRequest($options)
    {
        $params = new \DerivativeRequest(
            $this->getRequest(),
            $options
        );
        $api = new \ApiMain($params);
        $api->execute();

        return $api->getResult()->getResultData();
    }

    /**
     * Extract text content from an article.
     *
     * @param string $title Article title
     *
     * @return string
     */
    private function getTextFromArticle($title)
    {
        $title = \Title::newFromText($title);
        $revision = \Revision::newFromId($title->getLatestRevID());
        if (isset($revision)) {
            return $revision->getText();
        } else {
            return;
        }
    }

    /**
     * Parse a category tree.
     *
     * @param array $tree Category tree
     *
     * @return array Category list
     */
    private static function parseTree(array $tree)
    {
        $categories = [];
        foreach ($tree as $element => $parent) {
            if (!empty($parent)) {
                $categories = array_merge($categories, self::parseTree($parent));
            }
            $categories[] = $element;
        }

        return $categories;
    }

    /**
     * Get a category tree from an article.
     *
     * @param \Title $title Article title
     *
     * @return array Category tree
     */
    public static function getCategoryTree(\Title $title)
    {
        global $wgCountryCategory;
        $categories = self::parseTree($title->getParentCategoryTree());
        $return = '';
        if (isset($wgCountryCategory)
            && isset($categories[0])
            && preg_replace('/.+\:/', '', $categories[0]) == $wgCountryCategory
        ) {
            if (isset($categories[1])) {
                $catTitle = \Title::newFromText($categories[1]);
                $return .= \Linker::link($catTitle, htmlspecialchars($catTitle->getText()));
                if (isset($categories[2])) {
                    $catTitle = \Title::newFromText($categories[2]);
                    $return .= ' > '.\Linker::link($catTitle, htmlspecialchars($catTitle->getText()));
                }
            }
        }

        return $return;
    }

    /**
     * Display the special page.
     *
     * @param string $subPage
     *
     * @return void
     */
    public function execute($subPage)
    {
        global $wgCountryCategory, $wgTitle;
        $article = new \Article($wgTitle);
        $languageCode = $article->getContext()->getLanguage()->getCode();
        $output = $this->getOutput();
        $this->setHeaders();

        // Start heder row
        $output->addHTML('<div class="header-row">');
        //Lumière sur
        $focus = $this->getTextFromArticle('MediaWiki:ArchiHome-focus');
        if (isset($focus)) {
            $title = \Title::newFromText($focus);
            $output->addHTML('<div class="spotlight-container"><div class="spotlight-on"><header class="spotlight-header">');
            $wikitext = '=='.wfMessage('featured')->parse().'=='.PHP_EOL;
            $id = $title->getArticleID();
            if (isset($id) && $id > 0) {
                $extracts = $this->apiRequest(
                    [
                    'action'          => 'query',
                    'prop'            => 'extracts|images',
                    'titles'          => $title,
                    'explaintext'     => true,
                    'exchars'         => 120,
                    'exsectionformat' => 'plain',
                    'imlimit'         => 1,
                    ]
                );

                $wikitext .= '=== '.preg_replace('/\(.*\)/', '', $title->getText()).' ==='.PHP_EOL;
                $output->addWikiText($wikitext);
                $output->addHTML('<div class="breadcrumb">'.$this->getCategoryTree($title).'</div>');
                $output->addHTML('</header><div class="spotlight-content">');
                $wikitext = '';
                if (isset($extracts['query']['pages'][$id]['images'])) {
                    $wikitext .= '[['.$extracts['query']['pages'][$id]['images'][0]['title'].'|thumb|left|100px]]';
                }
                $wikitext .= PHP_EOL.$extracts['query']['pages'][$id]['extract']['*'].PHP_EOL.PHP_EOL.
                    '[['.$title.'|'.wfMessage('readthis')->parse().']]';
                $output->addWikiText($wikitext);
                $output->addHTML('<div style="clear:both;"></div>');
            }
            $output->addHTML('</div></div></div>');
        }
        $output->addHTML('</div>');

        //Recherche
        $output->addHTML(
            '<div class="search-box-row">
                <div class="search-box-container">
                    <nav class="search-box color-panel color-panel-earth">
                        <div class="row">
                            <div class="column">'
        );
        $output->addWikiText(
            '<h3 class="text-center search-title">'.wfMessage('searchdesc', '{{PAGESINNAMESPACE:'.NS_ADDRESS.'}}', '{{PAGESINNAMESPACE:6}}')->parse().'</h3>'
        );
        $output->addHTML(
            '</div>
        </div>'
        );
        $output->addHTML(
            '<div class="row">
                <div class="column large-7 large-offset-2">
                    <form id="searchform">
                        <div class="input-group">
                            <input type="search" class="search-input input-group-field" placeholder="'.wfMessage('search-placeholder')->parse().'" name="search">
                            <input type="hidden" name="title" value="Spécial:Recherche">
                            <div class="input-group-button">
                                <a class="button form-submit">
                                    <i class="material-icons">search</i>
                                </a>
                            </div>
                        </div>
        			</form>
                </div>
                <div class="column large-2 end">'
        );

        $output->addWikiText(
            '{{#queryformlink:form=Recherche avancée|link text='.wfMessage('advancedsearch')->parse().'}}'
        );
        $output->addHTML(
            '
                            </div>
                        </div>
                    </nav>
                </div>
            </div>'
        );

        $output->addHTML('<div class="association-block" data-equalizer data-equalize-on="medium">');
        //Qui sommes-nous ?
        $intro = $this->getTextFromArticle('MediaWiki:ArchiHome-about');
        $introTitle = $this->getTextFromArticle('MediaWiki:ArchiHome-about-title');
        if (isset($intro)) {
            $wikitext = '== '.$introTitle.' =='.PHP_EOL.
            $intro.PHP_EOL;
            $output->addHTML('<div class="archiwiki-intro-holder">');
            $output->addHTML('<section class="archiwiki-intro" data-equalizer-watch>');
            $output->addWikiText($wikitext);
            $output->addHTML('</section></div>');
        }

        //Actualités de l'association
        $news = $this->apiRequest(
            [
                'action'      => 'query',
                'list'        => 'recentchanges',
                'rcnamespace' => NS_NEWS,
                'rclimit'     => 1,
                'rctype'      => 'new',
            ]
        );
        if (isset($news['query']['recentchanges'][0])) {
            $title = \Title::newFromText($news['query']['recentchanges'][0]['title']);
            $extracts = $this->apiRequest(
                [
                'action'          => 'query',
                'prop'            => 'extracts|images',
                'titles'          => $news['query']['recentchanges'][0]['title'],
                'explaintext'     => true,
                'exintro'         => true,
                'exchars'         => 250,
                'exsectionformat' => 'plain',
                'imlimit'         => 1,
                ]
            );

            $wikitext = '== '.wfMessage('lastblog')->parse().'<br/>'.$title->getText().' =='.PHP_EOL;
            $wikitext .= '<div class="content-row">';
            if (isset($extracts['query']['pages'][$title->getArticleID()]['images'])) {
                $wikitext .= '[['.$extracts['query']['pages'][$title->getArticleID()]['images'][0]['title'].
                '|thumb|left|300px]]';
            }
            $wikitext .= '<div class="latest-news-text">'.$extracts['query']['pages'][$title->getArticleID()]['extract']['*'].PHP_EOL.PHP_EOL.
                '[['.$title->getFullText().'|'.wfMessage('readmore')->parse().']]'.PHP_EOL.PHP_EOL.
                '[[Special:ArchiBlog|'.wfMessage('othernews')->parse().']]'.'</div>';
            $wikitext .= '</div>';
            $output->addHTML('<div class="latest-news-holder">');
            $output->addHTML('<section class="latest-news" data-equalizer-watch>');
            $output->addWikiText($wikitext);
            $output->addHTML('<div style="clear:both;"></div>');
            $output->addHTML('</section></div>');
        }
        $output->addHTML('</div>'); // End of Association block

        $output->addHTML('<div class="latest-block">');
        //Dernières modifications
        $output->addHTML('<div class="latest-changes-container">');
        $output->addHTML('<section class="latest-changes">');

        $output->addWikiText(
            '== '.wfMessage('recentcontributions')->parse().' =='
        );

        $addresses = $this->apiRequest(
            [
                'action'      => 'query',
                'list'        => 'recentchanges',
                'rcnamespace' => NS_ADDRESS,
                'rctoponly'   => true,
            ]
        );
        $news = $this->apiRequest(
            [
                'action'      => 'query',
                'list'        => 'recentchanges',
                'rcnamespace' => NS_ADDRESS_NEWS,
                'rctoponly'   => true,
            ]
        );
        foreach ($addresses['query']['recentchanges'] as &$address) {
            foreach ($news['query']['recentchanges'] as &$article) {
                if (isset($address['title']) && isset($article['title'])) {
                    $addressTitle = \Title::newFromText($address['title']);
                    $articleTitle = \Title::newFromText($article['title']);
                    if ($addressTitle->getText() == $articleTitle->getText()) {
                        $addressRev = \Revision::newFromId($addressTitle->getLatestRevID());
                        $articleRev = \Revision::newFromId($articleTitle->getLatestRevID());
                        if ($articleRev->getTimestamp() > $addressRev->getTimestamp()) {
                            $parent = $address;
                            $address = $article;
                            $address['parent'] = $parent;
                        }
                    }
                }
            }
        }
        $changes = [];
        $i = 0;
        foreach ($addresses['query']['recentchanges'] as $change) {
            if ($i >= 6) {
                break;
            }
            if (isset($change['title'])) {
                $title = \Title::newFromText($change['title']);
                if ($title->getPageLanguage()->getCode() == $languageCode) {
                    $i++;
                    $id = $title->getArticleID();
                    if (isset($change['parent'])) {
                        $mainTitle = \Title::newFromText($change['parent']['title']);
                        $mainTitleId = $mainTitle->getArticleID();
                    } else {
                        $mainTitle = $title;
                        $mainTitleId = $id;
                    }

                    $extracts = $this->apiRequest(
                        [
                            'action'          => 'query',
                            'prop'            => 'extracts',
                            'titles'          => $change['title'],
                            'explaintext'     => true,
                            'exchars'         => 120,
                            'exsectionformat' => 'plain',
                        ]
                    );

                    $images = $this->apiRequest(
                        [
                            'action'  => 'query',
                            'prop'    => 'images',
                            'titles'  => $mainTitle,
                            'imlimit' => 1,
                        ]
                    );

                    $output->addHTML('<article class="latest-changes-recent-change-container">');
                    $output->addHTML('<article class="latest-changes-recent-change">');
                    $wikitext = '=== '.preg_replace('/\(.*\)/', '', $title->getText()).' ==='.PHP_EOL;
                    $output->addWikiText($wikitext);
                    $wikitext = '';
                    $output->addHTML($this->getCategoryTree($mainTitle));
                    if (isset($images['query']['pages'][$mainTitleId]['images'])) {
                        $wikitext .= '[['.$images['query']['pages'][$mainTitleId]['images'][0]['title'].
                            '|thumb|left|100px]]';
                    }
                    $wikitext .= PHP_EOL.$extracts['query']['pages'][$id]['extract']['*'].PHP_EOL.PHP_EOL.
                        '[['.$title->getFullText().'|'.wfMessage('readthis')->parse().']]';
                    $wikitext = str_replace("\t\t\n", '', $wikitext);
                    $output->addWikiText($wikitext);
                    $output->addHTML('<div style="clear:both;"></div></article></article>');
                }
            }
        }
        $output->addWikiText('[[Special:Modifications récentes|'.wfMessage('allrecentchanges')->parse().']]');
        $output->addHTML('</section></div>');

        //Derniers commentaires
        $output->addHTML('<div class="latest-comments-container">');
        $output->addHTML('<section class="latest-comments">');
        $output->addWikiText(
            '== '.wfMessage('recentcomments')->parse().' =='
        );

        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select(
            ['Comments', 'page'],
            ['Comment_Page_ID', 'Comment_Date', 'Comment_Text'],
            'page_id IS NOT NULL',
            null,
            ['ORDER BY' => 'Comment_Date DESC'],
            [
                'page' => [
                    'LEFT JOIN', 'Comment_Page_ID = page_id',
                ],
            ]
        );

        foreach ($res as $row) {
            if ($res->key() > 5) {
                break;
            }
            $title = \Title::newFromId($row->Comment_Page_ID);
            $output->addHTML('<div class="latest-comments-recent-comment-container">');
            $output->addHTML('<div class="latest-comments-recent-comment">');
            $output->addWikiText('=== '.preg_replace('/\(.*\)/', '', $title->getText()).' ==='.PHP_EOL);
            $output->addHTML($this->getCategoryTree($title));
            $wikitext = "''".strtok(wordwrap($row->Comment_Text, 170, '…'.PHP_EOL), PHP_EOL)."''".PHP_EOL.PHP_EOL.
                '[['.$title->getFullText().'#'.wfMessage('Comments')->parse().'|'.wfMessage('readthiscomment')->parse().']]';
            $output->addWikiText($wikitext);
            $output->addHTML('<div style="clear:both;"></div>');
            $output->addHTML('</div></div>');
        }

        $output->addWikiText('[[Special:ArchiComments|Tous les derniers commentaires]]');
        $output->addHTML('</section></div>');

        $output->addHTML('</div>'); // End of Latest block
    }

    /**
     * Return the special page category.
     *
     * @return string
     */
    public function getGroupName()
    {
        return 'pages';
    }
}

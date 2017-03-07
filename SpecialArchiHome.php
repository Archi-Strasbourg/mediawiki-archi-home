<?php

namespace ArchiHome;

class SpecialArchiHome extends \SpecialPage
{
    public function __construct()
    {
        parent::__construct('ArchiHome');
    }

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

    private static function parseTree($tree)
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

    public static function getCategoryTree($title)
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

    public function execute($subPage)
    {
        global $wgCountryCategory, $wgTitle;
        $article = new \Article($wgTitle);
        $languageCode = $article->getContext()->getLanguage()->getCode();
        $output = $this->getOutput();
        $this->setHeaders();

        //Lumière sur
        $focus = $this->getTextFromArticle('MediaWiki:ArchiHome-focus');
        if (isset($focus)) {
            $title = \Title::newFromText($focus);
            $output->addHTML('<div class="spotlight-on">');
            $wikitext = '==Lumière sur…=='.PHP_EOL;
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
                $wikitext = '';
                if (isset($extracts['query']['pages'][$id]['images'])) {
                    $wikitext .= '[['.$extracts['query']['pages'][$id]['images'][0]['title'].'|thumb|left|100px]]';
                }
                $wikitext .= PHP_EOL.$extracts['query']['pages'][$id]['extract']['*'].PHP_EOL.PHP_EOL.
                    '[['.$title.'|Découvrir cette fiche]]';
                $output->addWikiText($wikitext);
                $output->addHTML('<div style="clear:both;"></div>');
            }
            $output->addHTML('</div>');
        }

        //Recherche
        $output->addWikiText(
            'Recherchez à travers nos {{PAGESINNAMESPACE:'.NS_ADDRESS.'}} '.
            'adresses et {{PAGESINNAMESPACE:6}} photos&nbsp;:'
        );
        $output->addHTML(
            '<form id="searchform">
				<input type="search" placeholder="Indiquez une adresse, un nom de rue ou de bâtiment" name="search">
                <input type="hidden" name="title" value="Spécial:Recherche">
                <input type="submit" class="searchButton" value="Lire">
			</form>'
        );
        $output->addWikiText(
            '{{#queryformlink:form=Recherche avancée|link text=Recherche avancée}}'
        );

        //Qui sommes-nous ?
        $intro = $this->getTextFromArticle('MediaWiki:ArchiHome-about');
        $introTitle = $this->getTextFromArticle('MediaWiki:ArchiHome-about-title');
        if (isset($intro)) {
            $wikitext = '== '.$introTitle.' =='.PHP_EOL.
            $intro.PHP_EOL;
            $output->addWikiText($wikitext);
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

            $wikitext = '== Dernière actualité de de l\'association&nbsp;:<br/>'.$title->getText().' =='.PHP_EOL;
            if (isset($extracts['query']['pages'][$title->getArticleID()]['images'])) {
                $wikitext .= '[['.$extracts['query']['pages'][$title->getArticleID()]['images'][0]['title'].
                '|thumb|left|100px]]';
            }
            $wikitext .= $extracts['query']['pages'][$title->getArticleID()]['extract']['*'].PHP_EOL.PHP_EOL.
                '[['.$title->getFullText().'|Lire la suite]]'.PHP_EOL.PHP_EOL.
                '[[Special:ArchiBlog|Découvrir les autres actualités]]';
            $output->addWikiText($wikitext);
            $output->addHTML('<div style="clear:both;"></div>');
        }

        //Dernières modifications
        $output->addWikiText(
            '== Dernières modifications =='
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

                    $wikitext = '=== '.preg_replace('/\(.*\)/', '', $title->getText()).' ==='.PHP_EOL;
                    $output->addWikiText($wikitext);
                    $wikitext = '';
                    $output->addHTML($this->getCategoryTree($mainTitle));
                    if (isset($images['query']['pages'][$mainTitleId]['images'])) {
                        $wikitext .= '[['.$images['query']['pages'][$mainTitleId]['images'][0]['title'].
                            '|thumb|left|100px]]';
                    }
                    $wikitext .= PHP_EOL.preg_replace(
                        '/��[0-9]/',
                        '',
                        $extracts['query']['pages'][$id]['extract']['*']
                    ).PHP_EOL.PHP_EOL.
                        '[['.$title->getFullText().'|Consulter cette fiche]]';
                    $wikitext = str_replace("\t\t\n", '', $wikitext);
                    $output->addWikiText($wikitext);
                    $output->addHTML('<div style="clear:both;"></div>');
                }
            }
        }
        $output->addWikiText('[[Special:Modifications récentes|Toutes les dernières modifications]]');

        //Derniers commentaires
        $output->addWikiText(
            '== Derniers commentaires =='
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
            $output->addWikiText('=== '.preg_replace('/\(.*\)/', '', $title->getText()).' ==='.PHP_EOL);
            $output->addHTML($this->getCategoryTree($title));
            $wikitext = "''".strtok(wordwrap($row->Comment_Text, 170, '…'.PHP_EOL), PHP_EOL)."''".PHP_EOL.PHP_EOL.
                '[['.$title->getFullText().'#Commentaires|Consulter le commentaire]]';
            $output->addWikiText($wikitext);
            $output->addHTML('<div style="clear:both;"></div>');
        }

        $output->addWikiText('[[Special:ArchiComments|Tous les derniers commentaires]]');
    }

    public function getGroupName()
    {
        return 'pages';
    }
}

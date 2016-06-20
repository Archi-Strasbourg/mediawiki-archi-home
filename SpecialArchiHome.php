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
            return null;
        }
    }

    private function parseTree($tree)
    {
        $categories = array();
        foreach ($tree as $element => $parent) {
            if (!empty($parent)) {
                $categories = array_merge($categories, $this->parseTree($parent));
            }
            $categories[] = $element;
        }
        return $categories;
    }

    private function getCategoryTree($title)
    {
        global $wgCountryCategory;
        $categories = $this->parseTree($title->getParentCategoryTree());
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
        global $wgCountryCategory;
        $output = $this->getOutput();
        $this->setHeaders();

        //Lumière sur
        $focus = $this->getTextFromArticle('MediaWiki:ArchiHome-focus');
        if (isset($focus)) {
            $title = \Title::newFromText($focus);
            $wikitext = '==Lumière sur…=='.PHP_EOL;
            $id = $title->getArticleID();

            $extracts = $this->apiRequest(
                array(
                'action'=>'query',
                'prop'=>'extracts',
                'titles'=>$title,
                'explaintext'=>true,
                'exchars'=>120,
                'exsectionformat'=>'plain'
                )
            );

            $images = $this->apiRequest(
                array(
                'action'=>'query',
                'prop'=>'images',
                'titles'=>$title,
                'imlimit'=>1
                )
            );

            $wikitext .= '=== '.preg_replace('/\(.*\)/', '', $title->getText()).' ==='.PHP_EOL;
            $output->addWikiText($wikitext);
            $output->addHTML($this->getCategoryTree($title));
            $wikitext = '';
            if (isset($images['query']['pages'][$id]['images'])) {
                $wikitext .= '[['.$images['query']['pages'][$id]['images'][0]['title'].'|thumb|left|100px]]';
            }
            $wikitext .= $extracts['query']['pages'][$id]['extract']['*'].PHP_EOL.PHP_EOL.
                '[['.$title.'|Découvrir cette fiche]]';
            $output->addWikiText($wikitext);
            $output->addHTML('<div style="clear:both;"></div>');
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

        //Qui sommes-nous ?
        $intro = $this->getTextFromArticle('MediaWiki:ArchiHome-about');
        $introTitle = $this->getTextFromArticle('MediaWiki:ArchiHome-about-title');
        if (isset($intro)) {
            $wikitext = '== '.$introTitle.' =='.PHP_EOL.
            $intro.PHP_EOL.PHP_EOL.
            '[[Archi-Wiki:À propos|Découvrir l\'association]]';
            $output->addWikiText($wikitext);
        }

        //Actualités de l'association
        $news = $this->apiRequest(
            array(
                'action'=>'query',
                'list'=>'recentchanges',
                'rcnamespace'=>NS_NEWS,
                'rclimit'=>1,
                'rctype'=>'new'
            )
        );
        if (isset($news['query']['recentchanges'][0])) {
            $title = \Title::newFromText($news['query']['recentchanges'][0]['title']);
            $extracts = $this->apiRequest(
                array(
                'action'=>'query',
                'prop'=>'extracts',
                'titles'=>$news['query']['recentchanges'][0]['title'],
                'explaintext'=>true,
                'exintro'=>true,
                'exchars'=>250,
                'exsectionformat'=>'plain'
                )
            );

            $images = $this->apiRequest(
                array(
                'action'=>'query',
                'prop'=>'images',
                'titles'=>$title,
                'imlimit'=>1
                )
            );

            $wikitext = '== Dernière actualité de de l\'association&nbsp;:<br/>'.$title->getText().' =='.PHP_EOL;
            if (isset($images['query']['pages'][$title->getArticleID()]['images'])) {
                $wikitext .= '[['.$images['query']['pages'][$title->getArticleID()]['images'][0]['title'].
                '|thumb|left|100px]]';
            }
            $wikitext .= $extracts['query']['pages'][$title->getArticleID()]['extract']['*'].PHP_EOL.PHP_EOL.
                '[['.$title->getFullText().'|Lire la suite]]';
            $output->addWikiText($wikitext);
            $output->addHTML('<div style="clear:both;"></div>');
        }

        //Dernières modifications
        $output->addWikiText(
            '== Dernières modifications =='
        );

        $addresses = $this->apiRequest(
            array(
                'action'=>'query',
                'list'=>'recentchanges',
                'rcnamespace'=>NS_ADDRESS,
                'rclimit'=>6,
                'rctoponly'=>true
            )
        );
        $news = $this->apiRequest(
            array(
                'action'=>'query',
                'list'=>'recentchanges',
                'rcnamespace'=>NS_ADDRESS_NEWS,
                'rclimit'=>6,
                'rctoponly'=>true
            )
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
        $changes = array();
        foreach ($addresses['query']['recentchanges'] as $change) {
            if (isset($change['title'])) {
                $title = \Title::newFromText($change['title']);
                $id = $title->getArticleID();

                $extracts = $this->apiRequest(
                    array(
                        'action'=>'query',
                        'prop'=>'extracts',
                        'titles'=>$change['title'],
                        'explaintext'=>true,
                        'exchars'=>120,
                        'exsectionformat'=>'plain'
                    )
                );

                $images = $this->apiRequest(
                    array(
                        'action'=>'query',
                        'prop'=>'images',
                        'titles'=>$change['title'],
                        'imlimit'=>1
                    )
                );

                $wikitext = '=== '.preg_replace('/\(.*\)/', '', $title->getText()).' ==='.PHP_EOL;
                $output->addWikiText($wikitext);
                $wikitext = '';
                if (isset($change['parent'])) {
                    $mainTitle = \Title::newFromText($change['parent']['title']);
                } else {
                    $mainTitle = $title;
                }
                $output->addHTML($this->getCategoryTree($mainTitle));
                if (isset($images['query']['pages'][$id]['images'])) {
                    $wikitext .= '[['.$images['query']['pages'][$id]['images'][0]['title'].'|thumb|left|100px]]';
                }
                $wikitext .= $extracts['query']['pages'][$id]['extract']['*'].PHP_EOL.PHP_EOL.
                    '[['.$title->getFullText().'|Consulter cette fiche]]';
                $output->addWikiText($wikitext);
                $output->addHTML('<div style="clear:both;"></div>');
            }
        }

        //Derniers commentaires
        $output->addWikiText(
            '== Derniers commentaires =='
        );

        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->query(
            'SELECT Comment_Page_ID, Comment_Date, Comment_Text
            FROM (
                SELECT Comment_Page_ID, Comment_Date, Comment_Text
                FROM Comments
                ORDER BY Comment_Date DESC
            ) as Comments
            LEFT JOIN `page` ON ((Comment_Page_ID = page_id))
            WHERE (page_id IS NOT NULL)
            GROUP BY Comment_Page_ID
            ORDER BY Comment_Date DESC
            LIMIT 5;'
        );

        foreach ($res as $row) {
            $title = \Title::newFromId($row->Comment_Page_ID);
            $output->addWikiText('=== '.preg_replace('/\(.*\)/', '', $title->getText()).' ==='.PHP_EOL);
            $output->addHTML($this->getCategoryTree($title));
            $wikitext = "''".strtok(wordwrap($row->Comment_Text, 170, '…'.PHP_EOL), PHP_EOL)."''".PHP_EOL.PHP_EOL.
                '[['.$title->getFullText().'#Commentaires|Consulter le commentaire]]';
            $output->addWikiText($wikitext);
            $output->addHTML('<div style="clear:both;"></div>');
        }
    }

    public function getGroupName()
    {
           return 'pages';
    }
}

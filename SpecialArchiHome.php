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

    public function execute($par)
    {
        $output = $this->getOutput();
        $this->setHeaders();

        $title = \Title::newFromText('MediaWiki:ArchiHome-about');
        $revision = \Revision::newFromId($title->getLatestRevID());
        $wikitext = '== Qui sommes-nous&nbsp;? =='.PHP_EOL.
            $revision->getText().PHP_EOL.PHP_EOL.
            '[[Archi-Wiki:À propos|Découvrir l\'association]]';
        $output->addWikiText($wikitext);

        $news = $this->apiRequest(
            array(
                'action'=>'query',
                'list'=>'recentchanges',
                'rcnamespace'=>NS_NEWS,
                'rclimit'=>1,
                'rctype'=>'new'
            )
        );
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

        $wikitext = '== Actualités de l\'association =='.PHP_EOL.
            '=== '.$title->getText().' ==='.PHP_EOL.
            '['.\SpecialPage::getTitleFor('Toutes les pages')->getFullURL().
            '?namespace=4004 Toutes les actualités]'.PHP_EOL.PHP_EOL.
            $extracts['query']['pages'][$title->getArticleID()]['extract']['*'].PHP_EOL.PHP_EOL.
            '[['.$title->getFullText().'|Lire la suite]]';
        $output->addWikiText($wikitext);

        $output->addWikiText(
            'Recherchez parmi nos {{PAGESINNAMESPACE:'.NS_ADDRESS.'}} '.
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
                            $address = $article;
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
                if (isset($images['query']['pages'][$id]['images'])) {
                    $wikitext .= '[['.$images['query']['pages'][$id]['images'][0]['title'].'|thumb|left|100px]]';
                }
                $wikitext .= $extracts['query']['pages'][$id]['extract']['*'].PHP_EOL.PHP_EOL.
                    '[['.$title->getFullText().'|Découvrir cette fiche]]';
                $output->addWikiText($wikitext);
                $output->addHTML('<div style="clear:both;"></div>');
            }
        }
    }

    public function getGroupName()
    {
           return 'pages';
    }
}

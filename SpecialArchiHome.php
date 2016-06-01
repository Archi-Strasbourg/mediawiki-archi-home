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

        //Qui sommes-nous ?
        $title = \Title::newFromText('MediaWiki:ArchiHome-about');
        $revision = \Revision::newFromId($title->getLatestRevID());
	if (isset($revision)) {
        $wikitext = '== Qui sommes-nous&nbsp;? =='.PHP_EOL.
            $revision->getText().PHP_EOL.PHP_EOL.
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

        $wikitext = '== Actualités de l\'association =='.PHP_EOL.
            '[[Special:ArchiBlog|Toutes les actualités]]'.PHP_EOL.PHP_EOL.
            '=== '.$title->getText().' ==='.PHP_EOL;
        if (isset($images['query']['pages'][$title->getArticleID()]['images'])) {
            $wikitext .= '[['.$images['query']['pages'][$title->getArticleID()]['images'][0]['title'].
                '|thumb|left|100px]]';
        }
        $wikitext .= $extracts['query']['pages'][$title->getArticleID()]['extract']['*'].PHP_EOL.PHP_EOL.
            '[['.$title->getFullText().'|Lire la suite]]';
        $output->addWikiText($wikitext);
        $output->addHTML('<div style="clear:both;"></div>');
}

        //Recherche
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

        //Lumière sur
        $title = \Title::newFromText('MediaWiki:ArchiHome-focus');
        $revision = \Revision::newFromId($title->getLatestRevID());
if (isset($revision)) {
        $title = \Title::newFromText($revision->getText());
        $wikitext = '==Lumière sur=='.PHP_EOL;
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
        if (isset($images['query']['pages'][$id]['images'])) {
            $wikitext .= '[['.$images['query']['pages'][$id]['images'][0]['title'].'|thumb|left|100px]]';
        }
        $wikitext .= $extracts['query']['pages'][$id]['extract']['*'].PHP_EOL.PHP_EOL.
            '[['.$title.'|Découvrir cette fiche]]';
        $output->addWikiText($wikitext);
        $output->addHTML('<div style="clear:both;"></div>');
}

        //Image à la une
        $title = \Title::newFromText('MediaWiki:ArchiHome-focus-image');
        $revision = \Revision::newFromId($title->getLatestRevID());
if (isset($revision)) {
        $title = \Title::newFromText($revision->getText());
        $wikitext = '==Image à la une=='.PHP_EOL;
        $id = $title->getArticleID();

        $images = $this->apiRequest(
            array(
                'action'=>'query',
                'prop'=>'imageinfo',
                'titles'=>$title,
                'iiprop'=>'extmetadata'
            )
        );
        $wikitext .= '[['.$title.'|thumb|left|100px]]';
        if (isset($images['query']['pages'][$id]['imageinfo'][0]['extmetadata']['ImageDescription']['value'])) {
            $wikitext .= $images['query']['pages'][$id]['imageinfo'][0]['extmetadata']['ImageDescription']['value'];
        }
        $wikitext .= PHP_EOL.PHP_EOL.'[[:'.$title.'|Découvrir cette image]]';
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

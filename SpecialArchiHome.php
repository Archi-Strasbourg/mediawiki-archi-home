<?php
/**
 * SpecialArchiHome class.
 */

namespace ArchiHome;

use CategoryBreadcrumb\CategoryBreadcrumb;
use DateTime;
use Exception;
use Linker;
use MediaWiki\MediaWikiServices;
use MWException;
use Revision;
use Title;
use TextExtracts\ExtractFormatter;
use ConfigException;

/**
 * SpecialPage Special:ArchiHome that displays the custom homepage.
 */
class SpecialArchiHome extends \SpecialPage
{
    private $languageCode;

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
        $title = Title::newFromText($title);
        $revision = Revision::newFromId($title->getLatestRevID());
        if (isset($revision)) {
            return \ContentHandler::getContentText($revision->getContent(Revision::RAW));
        } else {
            return;
        }
    }

    /**
     * Get a category tree from an article.
     *
     * @param Title $title Article title
     *
     * @return string Category tree
     */
    public static function getCategoryTree(Title $title)
    {
        if ($title->getNamespace() == NS_ADDRESS_NEWS) {
            $title = Title::newFromText($title->getText(), NS_ADDRESS);
        }
        $parenttree = $title->getParentCategoryTree();
        CategoryBreadcrumb::checkParentCategory($parenttree);
        CategoryBreadcrumb::checkTree($parenttree);
        $flatTree = CategoryBreadcrumb::getFlatTree($parenttree);
        $return = '';
        $categories = array_reverse($flatTree);
        if (isset($categories[0])) {
            $catTitle = Title::newFromText($categories[0]);
            $return .= Linker::link($catTitle, htmlspecialchars($catTitle->getText()));
            if (isset($categories[1])) {
                $catTitle = Title::newFromText($categories[1]);
                $return .= ' > '. Linker::link($catTitle, htmlspecialchars($catTitle->getText()));
            }
        }

        return $return;
    }

    private function outputFocus()
    {
        $output = $this->getOutput();
        $focus = $this->getTextFromArticle('MediaWiki:ArchiHome-focus');
        if (isset($focus)) {
            $title = Title::newFromText($focus);
            if (!isset($title)) {
                return;
            }
            // Start header row
            $output->addHTML('<div class="header-row">');
            $output->addHTML('<div class="spotlight-container"><div class="spotlight-on"><header class="spotlight-header">');
            $wikitext = '=='.wfMessage('featured')->parse().'=='.PHP_EOL;
            $id = $title->getArticleID();
            if (isset($id) && $id > 0) {
                $extracts = $this->apiRequest(
                    [
                    'action'          => 'query',
                    'prop'            => 'extracts',
                    'titles'          => $title,
                    'explaintext'     => true,
                    'exchars'         => 120,
                    'exsectionformat' => 'plain',
                    'imlimit'         => 1,
                    ]
                );
                $images = $this->apiRequest(
                    [
                    'action'           => 'ask',
                    'query'            => '[['.$title.']]|?Image principale',
                    ]
                );

                $wikitext .= '=== '.preg_replace('/\(.*\)/', '', $title->getText()).' ==='.PHP_EOL;
                $output->addWikiText($wikitext);
                $output->addHTML('<div class="breadcrumb">'.$this->getCategoryTree($title).'</div>');
                $output->addHTML('</header><div class="spotlight-content">');
                $wikitext = '';
                if (isset($images['query']['results'][(string) $title]) && isset($images['query']['results'][(string) $title]['printouts']['Image principale'][0])) {
                    $wikitext .= '[['.$images['query']['results'][(string) $title]['printouts']['Image principale'][0]['fulltext'].'|thumb|left|100px]]';
                }
                $wikitext .= PHP_EOL.$extracts['query']['pages'][$id]['extract']['*'].PHP_EOL.PHP_EOL.
                    '[['.$title.'|'.wfMessage('readthis')->parse().']]';
                $output->addWikiText($wikitext);
                $output->addHTML('<div style="clear:both;"></div>');
            }
            $output->addHTML('</div></div></div>');
        }
        $output->addHTML('</div>');
    }

    private function outputSearch()
    {
        global $wgScript;
        $output = $this->getOutput();
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
                    <form id="searchform" action="'.$wgScript.'">
                        <div class="input-group">
                            <input type="search" class="mw-searchInput search-input input-group-field" placeholder="'.wfMessage('search-placeholder')->parse().'" name="search">
                            <input type="hidden" name="title" value="Spécial:Recherche">
                            <div class="input-group-button">
                                <a class="button form-submit">
                                    <i class="material-icons">search</i>
                                </a>
                            </div>
                        </div>
        			</form>
                </div>
                <div class="column large-3 end">'
        );

        $output->addWikiText(
            '{{#queryformlink:form=Recherche avancée|link text='.wfMessage('advancedsearch')->parse().'}}'.
            '<br/>'.
            '[[Carte globale|Recherche cartographique]]'.
            '<br/>'.
            '[[Spécial:Nearby|À proximité]]'
        );
        $output->addHTML(
            '
                            </div>
                        </div>
                    </nav>
                </div>
            </div>'
        );
    }

    /**
     * @throws MWException
     */
    private function outputAbout()
    {
        $output = $this->getOutput();
        $intro = $this->getTextFromArticle('Archi-Wiki, c\'est quoi ?');
        $introTitle = $this->getTextFromArticle('MediaWiki:ArchiHome-about-title');
        if (isset($intro)) {
            $wikitext = '== '.$introTitle.' =='.PHP_EOL.
            $intro.PHP_EOL;
            $output->addHTML('<div class="archiwiki-intro-holder">');
            $output->addHTML('<section class="archiwiki-intro" data-equalizer-watch>');
            $output->addWikiText($wikitext);
            $output->addHTML('</section></div>');
        }
    }

    private function outputNews()
    {
        $output = $this->getOutput();
        $news = $this->apiRequest(
            [
                'action'      => 'query',
                'list'        => 'recentchanges',
                'rcnamespace' => NS_NEWS,
                'rclimit'     => 1,
            ]
        );
        if (isset($news['query']['recentchanges'][0])) {
            $title = Title::newFromText($news['query']['recentchanges'][0]['title']);
            $extracts = $this->apiRequest(
                [
                'action'          => 'query',
                'prop'            => 'extracts|pageimages',
                'titles'          => $news['query']['recentchanges'][0]['title'],
                'explaintext'     => true,
                'exintro'         => true,
                'exchars'         => 250,
                'exsectionformat' => 'plain',
                ]
            );

            $wikitext = '== '.wfMessage('lastblog')->parse().'<br/>'.$title->getText().' =='.PHP_EOL;
            $wikitext .= '<div class="content-row">';
            if (isset($extracts['query']['pages'][$title->getArticleID()]['pageimage'])) {
                $wikitext .= '[[File:'.$extracts['query']['pages'][$title->getArticleID()]['pageimage'].
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
    }

    /**
     * @param $a
     * @param $b
     * @return int
     * @throws Exception
     */
    private function sortChanges($a, $b)
    {
        $dateA = new DateTime($a['timestamp']);
        $dateB = new DateTime($b['timestamp']);

        if ($dateA == $dateB) {
            return 0;
        }

        return ($dateA > $dateB) ? -1 : 1;
    }

    /**
     * @param $text
     * @return string
     * @throws ConfigException
     */
    private function convertText($text) {
        $fmt = new ExtractFormatter(
            $text,
            TRUE,
            MediaWikiServices::getInstance()
                ->getConfigFactory()
                ->makeConfig('textextracts')
        );

        $text = trim(
            preg_replace(
                "/" . ExtractFormatter::SECTION_MARKER_START . '(\d)' . ExtractFormatter::SECTION_MARKER_END . "(.*?)$/m",
                '',
                ExtractFormatter::getFirstChars($fmt->getText(), 120)
            )
        );
        if (!empty($text)) {
            $text .= wfMessage('ellipsis')->inContentLanguage()->text();
        }

        return $text;
    }

    /**
     * @param Title $title
     * @param $section
     * @return mixed|string
     * @throws ConfigException
     */
    private function getExtract(Title $title, $section) {
        global $wgMemc;

        $id = $title->getArticleID();

        $key = wfMemcKey('archidescription', $id, $section, $title->getTouched());
        $result = $wgMemc->get($key);

        if (!$result) {
            // On refait manuellement ce que fait TextExtracts pour pouvoir le faire sur la section 1.
            $extracts = $this->apiRequest(
                [
                    'action' => 'parse',
                    'pageid' => $id,
                    'prop' => 'text',
                    'section' => $section,
                ]
            );

            $result = '';
            if (isset($extracts['parse']['text'])) {
                $result = $this->convertText($extracts['parse']['text']);
            }

            $wgMemc->set($key, $result);
        }

        return $result;
    }

    /**
     * @throws ConfigException
     * @throws MWException
     * @throws Exception
     */
    private function outputRecentChanges()
    {
        $output = $this->getOutput();
        $output->addHTML('<div class="latest-changes-container">');
        $output->addHTML('<section class="latest-changes">');

        $output->addWikiText(
            '== '.wfMessage('recentcontributions')->parse().' =='
        );

        $addresses = $this->apiRequest(
            [
                'action'      => 'query',
                'list'        => 'recentchanges',
                'rcnamespace' => NS_ADDRESS.'|'.NS_PERSON,
                'rctoponly'   => true,
                'rcshow'      => '!redirect',
            ]
        );
        $news = $this->apiRequest(
            [
                'action'      => 'query',
                'list'        => 'recentchanges',
                'rcnamespace' => NS_ADDRESS_NEWS,
                'rctoponly'   => true,
                'rcshow'      => '!redirect',
            ]
        );
        foreach ($addresses['query']['recentchanges'] as &$address) {
            foreach ($news['query']['recentchanges'] as &$article) {
                if (isset($address['title']) && isset($article['title'])) {
                    $addressTitle = Title::newFromText($address['title']);
                    $articleTitle = Title::newFromText($article['title']);
                    if ($addressTitle->getText() == $articleTitle->getText()) {
                        $addressRev = Revision::newFromId($addressTitle->getLatestRevID());
                        $articleRev = Revision::newFromId($articleTitle->getLatestRevID());
                        if ($articleRev->getTimestamp() > $addressRev->getTimestamp()) {
                            $parent = $address;
                            $address = $article;
                            $address['parent'] = $parent;
                        }
                    }
                }
            }
        }
        unset($addresses['query']['recentchanges']['_element']);
        usort($addresses['query']['recentchanges'], [$this, 'sortChanges']);

        $i = 0;
        foreach ($addresses['query']['recentchanges'] as $change) {
            if ($i >= 6) {
                break;
            }

            if (isset($change['title'])) {
                $title = Title::newFromText($change['title']);

                //Il faudra peut être utiliser $title->getPageLanguage()->getCode() quand Translate sera activé
                $titleLanguageCode = $title->getSubpageText();

                if ($titleLanguageCode == $title->getBaseText()) {
                    $titleLanguageCode = 'fr';
                }

                if ($titleLanguageCode == $this->languageCode) {
                    $i++;
                    $id = $title->getArticleID();
                    if (isset($change['parent'])) {
                        $mainTitle = Title::newFromText($change['parent']['title']);
                    } else {
                        $mainTitle = $title;
                    }

                    $revision = Revision::newFromTitle($title);
                    preg_match('#/\*(.*)\*/#', $revision->getComment(), $matches);
                    $sectionName = str_replace(
                        '[', '<sup>',
                        str_replace(
                            ']', '</sup>',
                            trim($matches[1])
                        )
                    );

                    $extract = null;
                    $sectionNumber = null;

                    // On essaie d'avoir un extrait de la section modifiée.
                    if (!empty($sectionName)) {
                        $sections = $this->apiRequest(
                            [
                                'action' => 'parse',
                                'page' => $change['title'],
                                'prop' => 'sections',
                            ]
                        );

                        foreach ($sections['parse']['sections'] as $section) {
                            if ($section['line'] == $sectionName) {
                                $sectionNumber = $section['index'];
                            }
                        }

                        if (isset($sectionNumber)) {
                            $extract = $this->getExtract($title, $sectionNumber);
                        }
                    }

                    // Si on prend le début de l'article.
                    if (!isset($extract)) {
                        $extracts = $this->apiRequest(
                            [
                                'action' => 'query',
                                'prop' => 'extracts',
                                'titles' => $change['title'],
                                'explaintext' => true,
                                'exchars' => 120,
                                'exsectionformat' => 'plain',
                            ]
                        );

                        $extract = $extracts['query']['pages'][$id]['extract']['*'];
                    }

                    $properties = $this->apiRequest(
                        [
                        'action'           => 'ask',
                        'query'            => '[['.$mainTitle.']]|?Image principale|?Adresse complète',
                        ]
                    );

                    $output->addHTML('<article class="latest-changes-recent-change-container">');
                    $output->addHTML('<article class="latest-changes-recent-change">');
                    $wikitext = '=== '.preg_replace('/\(.*\)/', '', $title->getBaseText()).' ==='.PHP_EOL;
                    $output->addWikiText($wikitext);
                    if (isset($properties['query']['results'][(string) $mainTitle]) && !empty($properties['query']['results'][(string) $mainTitle]['printouts']['Adresse complète'])) {
                        $output->addWikiText($properties['query']['results'][(string) $mainTitle]['printouts']['Adresse complète'][0]['fulltext']);
                    }
                    $output->addHTML($this->getCategoryTree($mainTitle));

                    if (isset($properties['query']['results'][(string) $mainTitle]) && !empty($properties['query']['results'][(string) $mainTitle]['printouts']['Image principale'])) {
                        $output->addWikiText('[['.$properties['query']['results'][(string) $mainTitle]['printouts']['Image principale'][0]['fulltext'].
                            '|thumb|left|100px]]');
                    }

                    $date = new DateTime($change['timestamp']);
                    $output->addWikiText("''".strftime('%x', $date->getTimestamp())."''");

                    $output->addHTML('<p>'.$extract.'</p>');
                    $wikitext ='[['.$title->getFullText().'|'.wfMessage('readthis')->parse().']]';
                    $wikitext = str_replace("\t\t\n", '', $wikitext);
                    $output->addWikiText($wikitext);
                    $output->addHTML('<div style="clear:both;"></div></article></article>');
                }
            }
        }
        $output->addWikiText('[[Special:Modifications récentes|'.wfMessage('allrecentchanges')->parse().']]');
        $output->addHTML('</section></div>');
    }

    private function outputRecentComments()
    {
        $output = $this->getOutput();
        $output->addHTML('<div class="latest-comments-container">');
        $output->addHTML('<section class="latest-comments">');
        $output->addWikiText(
            '== '.wfMessage('recentcomments')->parse().' =='
        );

        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select(
            ['Comments', 'page'],
            ['CommentID', 'Comment_Page_ID', 'Comment_Date', 'Comment_Text', 'Comment_Username'],
            'page_id IS NOT NULL',
            null,
            ['ORDER BY' => 'Comment_Date DESC'],
            [
                'page' => [
                    'LEFT JOIN', 'Comment_Page_ID = page_id',
                ],
            ]
        );

        $i = 0;
        foreach ($res as $row) {
            if ($i > 5) {
                break;
            }
            $title = Title::newFromId($row->Comment_Page_ID);
            $titleLanguageCode = $title->getSubpageText();
            if ($titleLanguageCode == $title->getBaseText()) {
                $titleLanguageCode = 'fr';
            }
            if ($titleLanguageCode == $this->languageCode) {
                $user = \User::newFromName($row->Comment_Username);
                $date = new DateTime($row->Comment_Date);
                $output->addHTML('<div class="latest-comments-recent-comment-container">');
                $output->addHTML('<div class="latest-comments-recent-comment">');
                $output->addWikiText('=== '.preg_replace('/\(.*\)/', '', $title->getBaseText()).' ==='.PHP_EOL);
                $output->addHTML($this->getCategoryTree($title));
                $output->addWikiText('Par [[Utilisateur:'.$user->getName().'|'.$user->getName().']] le '.strftime('%x', $date->getTimestamp()));
                $wikitext = "''".strtok(wordwrap($row->Comment_Text, 170, '…'.PHP_EOL), PHP_EOL)."''".PHP_EOL.PHP_EOL.
                    '[['.$title->getFullText().'#comment-'.$row->CommentID.'|'.wfMessage('readthiscomment')->parse().']]';
                $output->addWikiText($wikitext);
                $output->addHTML('<div style="clear:both;"></div>');
                $output->addHTML('</div></div>');
            }
            $i++;
        }

        $output->addWikiText('[[Special:ArchiComments|Tous les derniers commentaires]]');
        $output->addHTML('</section></div>');
    }

    /**
     * Set the robot policy.
     *
     * @return string
     */
    protected function getRobotPolicy()
    {
        return 'index,follow';
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
        $this->languageCode = $article->getContext()->getLanguage()->getCode();

        $output = $this->getOutput();
        $this->setHeaders();

        //Lumière sur
        $this->outputFocus();

        //Recherche
        $this->outputSearch();

        $output->addHTML('<div class="association-block" data-equalizer data-equalize-on="medium">');

        //Qui sommes-nous ?
        $this->outputAbout();

        //Actualités de l'association
        $this->outputNews();

        $output->addHTML('</div>'); // End of Association block

        $output->addHTML('<div class="latest-block">');

        //Dernières modifications
        $this->outputRecentChanges();

        //Derniers commentaires
        $this->outputRecentComments();

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

<?php
/**
 * SpecialArchiHome class.
 */

namespace ArchiHome;

use ApiMain;
use Article;
use CategoryBreadcrumb\CategoryBreadcrumb;
use ContentHandler;
use DateTime;
use DerivativeContext;
use DerivativeRequest;
use Exception;
use Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MobileContext;
use MWException;
use ObjectCache;
use RequestContext;
use SMW\Services\ServicesFactory as ApplicationFactory;
use SpecialPage;
use TextExtracts\TextTruncator;
use Title;
use TextExtracts\ExtractFormatter;
use ConfigException;
use User;

/**
 * SpecialPage Special:ArchiHome that displays the custom homepage.
 */
class SpecialArchiHome extends SpecialPage
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
    private function apiRequest(array $options): array
    {
        $params = new DerivativeRequest(
            $this->getRequest(),
            $options
        );
        $api = new ApiMain($params);

        /** @var MobileContext $mobileContext */
        $mobileContext = MediaWikiServices::getInstance()->getService('MobileFrontend.Context');

        /*
         * MobileFrontendHooks ne détecte pas qu'il est dans une sous-requête d'API
         * et il injecte un JS au début du HTML.
         * Pour éviter ça, on lui indique explicitement le contexte.
         */
        $context = new DerivativeContext($mobileContext->getContext());
        $context->setRequest(new DerivativeRequest($context->getRequest(), $options));
        $mobileContext->setContext($context);

        $api->execute();

        return $api->getResult()->getResultData();
    }

    /**
     * Extract text content from an article.
     *
     * @param string $title Article title
     *
     * @return string
     * @throws MWException
     */
    private function getTextFromArticle(string $title): string
    {
        $title = Title::newFromText($title);
        $revision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionById($title->getLatestRevID());
        if (isset($revision)) {
            $content = $revision->getContent(SlotRecord::MAIN, RevisionRecord::RAW);
            if ($content instanceof \TextContent) {
                return $content->getText();
            }
        }

        return '';
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
                $return .= ' > ' . Linker::link($catTitle, htmlspecialchars($catTitle->getText()));
            }
        }

        return $return;
    }

    /**
     * @throws MWException
     */
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
            $wikitext = '==' . wfMessage('featured')->parse() . '==' . PHP_EOL;
            $id = $title->getArticleID();
            if (isset($id) && $id > 0) {
                $extracts = $this->apiRequest(
                    [
                        'action' => 'query',
                        'prop' => 'extracts',
                        'titles' => $title->getFullText(),
                        'explaintext' => true,
                        'exchars' => 120,
                        'exsectionformat' => 'plain',
                        'exlimit' => 1,
                    ]
                );
                $images = $this->apiRequest(
                    [
                        'action' => 'ask',
                        'query' => '[[' . $title . ']]|?Image principale texte',
                    ]
                );

                $wikitext .= '=== ' . preg_replace('/\(.*\)/', '', $title->getText()) . ' ===' . PHP_EOL;
                $output->addWikiTextAsContent($wikitext);
                $output->addHTML('<div class="breadcrumb">' . $this->getCategoryTree($title) . '</div>');
                $output->addHTML('</header><div class="spotlight-content">');
                $wikitext = '';
                if (isset($images['query']['results'][(string)$title]) && isset($images['query']['results'][(string)$title]['printouts']['Image principale texte'][0])) {
                    $wikitext .= '[[' . $images['query']['results'][(string)$title]['printouts']['Image principale texte'][0] . '|thumb|left|100px]]';
                }
                if (isset($extracts['query']['pages'][$id])) {
                    $wikitext .= PHP_EOL . $extracts['query']['pages'][$id]['extract']['*'] . PHP_EOL . PHP_EOL .
                        '[[' . $title . '|' . wfMessage('readthis')->parse() . ']]';
                }
                $output->addWikiTextAsContent($wikitext);
                $output->addHTML('<div style="clear:both;"></div>');
            }
            $output->addHTML('</div></div></div>');
        }
        $output->addHTML('</div>');
    }

    /**
     * @throws MWException
     */
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
        $output->addWikiTextAsInterface(
            '<h3 class="text-center search-title">' .
            wfMessage(
                'searchdesc',
                '{{PAGESINNAMESPACE:' . NS_ADDRESS . '}}',
                '{{PAGESINNAMESPACE:6}}',
                '{{PAGESINNAMESPACE:' . NS_PERSON . '}}'
            )->parse() .
            '</h3>'
        );
        $output->addHTML(
            '</div>
        </div>'
        );
        $output->addHTML(
            '<div class="row">
                <div class="column large-7 large-offset-2">
                    <form id="searchform" action="' . $wgScript . '">
                        <div class="input-group">
                            <input type="search" class="mw-searchInput search-input input-group-field" placeholder="' . wfMessage('search-placeholder')->parse() . '" name="search">
                            <input type="hidden" name="title" value="Spécial:Recherche">
                            <input type="hidden" name="profile" value="default">
                            <div class="input-group-button">
                                <a class="button form-submit">
                                    <i class="material-icons">search</i>
                                </a>
                            </div>
                        </div>
                    </form>' .
            MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
                Title::newFromText('Special:Random'),
                'Article au hasard !',
                ['class' => 'link--random']
            ) .
            '</div>
                <div class="column large-3 end">'
        );

        $output->addWikiTextAsInterface(
            '{{#querycacheformlink:form=Recherche avancée|link text=' . wfMessage('advancedsearch')->parse() . '}}' .
            '<br/>' .
            '{{#querycacheformlink:form=Recherche avancée|link text=Recherche cartographique|pfRunQueryFormName=Recherche avancée|Recherche avancée[carte][value]=1|Recherche avancée[carte][is_checkbox]=true}}' .
            '<br/>' .
            '<span class="link--nearby">[[Spécial:Nearby|À proximité]]</span>'
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
    private function outputBriefs()
    {
        $output = $this->getOutput();

        $output->addHTML('<div class="archiwiki-intro-holder">');
        $output->addHTML('<section class="archiwiki-intro" data-equalizer-watch>');
        $output->addWikiTextAsInterface("== Fil d'actualité ==");
        $output->addWikiTextAsContent('{{#ask:
            [[Brève:+]]
            |?Date de publication#ISO
            |?URL
            |?Titre actualité
            |format=ul
            |template=Affichage brève
            |link=none
            |sort=Date de publication
            |order=desc
            |limit=5
            |searchlabel=
            }}
        ');
        $output->addWikiTextAsInterface("[[Fil d'actualité|" . wfMessage('allbriefs')->parse() . ']]');
        $output->addHTML('<div style="clear:both;"></div>');
        $output->addHTML('</section></div>');
    }

    /**
     * @throws MWException
     */
    private function outputNews()
    {
        $output = $this->getOutput();
        $news = $this->apiRequest(
            [
                'action' => 'query',
                'list' => 'recentchanges',
                'rcnamespace' => implode('|', [NS_NEWS, NS_ARTICLE]),
                'rclimit' => 1,
            ]
        );
        if (isset($news['query']['recentchanges'][0])) {
            $title = Title::newFromText($news['query']['recentchanges'][0]['title']);
            $extracts = $this->apiRequest(
                [
                    'action' => 'query',
                    'prop' => 'extracts|pageimages',
                    'titles' => $news['query']['recentchanges'][0]['title'],
                    'explaintext' => true,
                    'exintro' => true,
                    'exchars' => 250,
                    'exsectionformat' => 'plain',
                ]
            );

            $wikitext = '== ' . wfMessage('lastblog')->parse() . '<br/>' . $title->getText() . ' ==' . PHP_EOL;
            $wikitext .= '<div class="content-row">';
            if (isset($extracts['query']['pages'][$title->getArticleID()]['pageimage'])) {
                $wikitext .= '[[File:' . $extracts['query']['pages'][$title->getArticleID()]['pageimage'] .
                    '|thumb|left|300px]]';
            }
            if ($title->getNamespace() == NS_NEWS) {
                $otherText = wfMessage('othernews')->parse();
            }
            else {
                $otherText = 'Découvrir les autres articles';
            }
            $wikitext .= '<div class="latest-news-text">' . $extracts['query']['pages'][$title->getArticleID()]['extract']['*'] . PHP_EOL . PHP_EOL .
                '[[' . $title->getFullText() . '|' . wfMessage('readmore')->parse() . ']]' . PHP_EOL . PHP_EOL .
                '[[Special:ArchiBlog|' . $otherText . ']]' . '</div>';
            $wikitext .= '</div>';
            $output->addHTML('<div class="latest-news-holder">');
            $output->addHTML('<section class="latest-news" data-equalizer-watch>');
            $output->addWikiTextAsContent($wikitext);
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
    private function convertText($text): string
    {
        $fmt = new ExtractFormatter(
            $text,
            TRUE,
        );

        $truncator = new TextTruncator(false);

        $text = trim(
            preg_replace(
                "/" . ExtractFormatter::SECTION_MARKER_START . '(\d)' . ExtractFormatter::SECTION_MARKER_END . "(.*?)$/m",
                '',
                $truncator->getFirstChars($fmt->getText(), 120)
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
    private function getExtract(Title $title, $section)
    {
        $cache = ObjectCache::getLocalClusterInstance();

        $id = $title->getArticleID();

        $key = $cache->makeKey('archidescription', $id, $section, $title->getTouched());
        $result = $cache->get($key);

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

            $cache->set($key, $result);
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
        global $wgDBname;

        $output = $this->getOutput();
        $output->addHTML('<div class="latest-changes-container">');
        $output->addHTML('<section class="latest-changes">');

        $output->addWikiTextAsInterface(
            '== ' . wfMessage('recentcontributions')->parse() . ' =='
        );

        $addresses = $this->apiRequest(
            [
                'action' => 'query',
                'list' => 'recentchanges',
                'rcnamespace' => NS_ADDRESS . '|' . NS_PERSON,
                'rctoponly' => true,
                'rcshow' => '!redirect',
                'rclimit' => 50
            ]
        );
        $news = $this->apiRequest(
            [
                'action' => 'query',
                'list' => 'recentchanges',
                'rcnamespace' => NS_ADDRESS_NEWS,
                'rctoponly' => true,
                'rcshow' => '!redirect',
                'rclimit' => 50
            ]
        );
        foreach ($addresses['query']['recentchanges'] as &$address) {
            foreach ($news['query']['recentchanges'] as &$article) {
                if (isset($address['title']) && isset($article['title'])) {
                    $addressTitle = Title::newFromText($address['title']);
                    $articleTitle = Title::newFromText($article['title']);
                    if ($addressTitle->getText() == $articleTitle->getText()) {
                        $revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
                        $addressRev = $revisionLookup->getRevisionById($addressTitle->getLatestRevID());
                        $articleRev = $revisionLookup->getRevisionById($articleTitle->getLatestRevID());
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

            if (isset($change['title']) && $change['title'] != 'Adresse:Bac à sable') {
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

                    $revision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle($title);
                    preg_match('#/\*(.*)\*/#', $revision->getComment()->text, $matches);
                    if (isset($matches[1])) {
                        $sectionName = str_replace(
                            '[', '<sup>',
                            str_replace(
                                ']', '</sup>',
                                trim($matches[1])
                            )
                        );
                    }

                    $extract = null;
                    $sectionNumber = null;

                    // On essaie d'avoir un extrait de la section modifiée.
                    if (!empty($sectionName)) {
                        $cache = ObjectCache::getInstance('redis');
                        $cacheKey = implode(
                            ':',
                            [
                                // On met en cache par révision.
                                $wgDBname,
                                'archi-home',
                                'parse-section',
                                $revision->getId()
                            ]
                        );
                        $sections = $cache->get($cacheKey);

                        if ($sections === FALSE) {
                            $sections = $this->apiRequest(
                                [
                                    'action' => 'parse',
                                    'page' => $change['title'],
                                    'prop' => 'sections',
                                ]
                            );
                            // Cet appel est couteux donc on met le résultat en cache.
                            $cache->set($cacheKey, $sections, $cache::TTL_YEAR);
                        }

                        foreach ($sections['parse']['sections'] as $section) {
                            if (isset($section['line']) && $section['line'] == $sectionName) {
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
                            'action' => 'ask',
                            'query' => '[[' . $mainTitle . ']]|?Image principale texte|?Adresse complète',
                        ]
                    );

                    $output->addHTML('<article class="latest-changes-recent-change-container">');
                    $output->addHTML('<article class="latest-changes-recent-change">');
                    $wikitext = '=== ' . preg_replace('/\(.*\)/', '', $title->getBaseText()) . ' ===' . PHP_EOL;
                    $output->addWikiTextAsContent($wikitext);
                    if (isset($properties['query']['results'][(string)$mainTitle]) && !empty($properties['query']['results'][(string)$mainTitle]['printouts']['Adresse complète'])) {
                        $output->addWikiTextAsContent($properties['query']['results'][(string)$mainTitle]['printouts']['Adresse complète'][0]['fulltext']);
                    }
                    $output->addHTML($this->getCategoryTree($mainTitle));

                    if (isset($properties['query']['results'][(string)$mainTitle]) && !empty($properties['query']['results'][(string)$mainTitle]['printouts']['Image principale texte'])) {
                        $output->addWikiTextAsContent('[[' . $properties['query']['results'][(string)$mainTitle]['printouts']['Image principale texte'][0] .
                            '|thumb|left|100px]]');
                    }

                    $date = new DateTime($change['timestamp']);
                    $output->addWikiTextAsContent("''" . $date->format('d/m/Y') . "''");

                    $output->addHTML('<p>' . $extract . '</p>');
                    $wikitext = '[[' . $title->getFullText() . '|' . wfMessage('readthis')->parse() . ']]';
                    $wikitext = str_replace("\t\t\n", '', $wikitext);
                    $output->addWikiTextAsInterface($wikitext);
                    $output->addHTML('<div style="clear:both;"></div></article></article>');
                }
            }
        }
        $output->addWikiTextAsInterface('[[Special:ArchiRecentChanges|' . wfMessage('allrecentchanges')->parse() . ']]');
        $output->addHTML('</section></div>');
    }

    /**
     * @throws MWException
     */
    private function outputRecentComments()
    {
        $output = $this->getOutput();
        $output->addHTML('<div class="latest-comments-container">');
        $output->addHTML('<section class="latest-comments">');
        $output->addWikiTextAsInterface(
            '== ' . wfMessage('recentcomments')->parse() . ' =='
        );

        $dbr = MediaWikiServices::getInstance()
            ->getDBLoadBalancer()->getConnection(DB_REPLICA);
        $res = $dbr->select(
            ['Comments', 'page'],
            ['CommentID', 'Comment_Page_ID', 'Comment_Date', 'Comment_Text', 'Comment_actor'],
            'page_id IS NOT NULL',
            __METHOD__,
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
                $user = User::newFromActorId($row->Comment_actor);
                $date = new DateTime($row->Comment_Date);
                $output->addHTML('<div class="latest-comments-recent-comment-container">');
                $output->addHTML('<div class="latest-comments-recent-comment">');
                $output->addWikiTextAsContent('=== ' . preg_replace('/\(.*\)/', '', $title->getBaseText()) . ' ===' . PHP_EOL);
                $output->addHTML($this->getCategoryTree($title));
                $output->addWikiTextAsContent('Par [[Utilisateur:' . $user->getName() . '|' . $user->getName() . ']] le ' . $date->format('d/m/Y'));
                $wikitext = "''" . strtok(wordwrap($row->Comment_Text, 170, '…' . PHP_EOL), PHP_EOL) . "''" . PHP_EOL . PHP_EOL .
                    '[[' . $title->getFullText() . '#comment-' . $row->CommentID . '|' . wfMessage('readthiscomment')->parse() . ']]';
                $output->addWikiTextAsInterface($wikitext);
                $output->addHTML('<div style="clear:both;"></div>');
                $output->addHTML('</div></div>');
            }
            $i++;
        }

        $output->addWikiTextAsInterface('[[Special:ArchiComments|Tous les derniers commentaires]]');
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
     * @throws MWException
     */
    public function execute($subPage)
    {
        $this->languageCode = RequestContext::getMain()->getLanguage()->getCode();

        $output = $this->getOutput();
        $this->setHeaders();

        //Lumière sur
        $this->outputFocus();

        //Recherche
        $this->outputSearch();

        $output->addHTML('<div class="association-block" data-equalizer data-equalize-on="medium">');

        // Éditoriaux
        $this->outputNews();

        // Brèves
        $this->outputBriefs();

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

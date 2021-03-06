<?php

namespace Claroline\CoreBundle\Twig;

use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @DI\Service
 * @DI\Tag("twig.extension")
 */
class HomeExtension extends \Twig_Extension
{
    protected $container;
    protected $kernel;

    /**
     * @DI\InjectParams({
     *     "container" = @DI\Inject("service_container")
     * })
     */
    public function __construct(KernelInterface $kernel, $container)
    {
        $this->kernel = $kernel;
        $this->container = $container;
    }

    /**
     * Get filters of the service
     *
     * @return \Twig_Filter_Method
     */
    public function getFilters()
    {
        return array(
            'timeAgo' => new \Twig_Filter_Method($this, 'timeAgo'),
            'homeLink' => new \Twig_Filter_Method($this, 'homeLink'),
            'activeLink' => new \Twig_Filter_Method($this, 'activeLink'),
            'compareRoute' => new \Twig_Filter_Method($this, 'compareRoute'),
            'autoLink' => new \Twig_Filter_Method($this, 'autoLink')
        );
    }

    public function getFunctions()
    {
        return array(
            'isDesktop' => new \Twig_Function_Method($this, 'isDesktop'),
            'asset_exists' => new \Twig_Function_Method($this, 'asset_exists')
        );
    }

    /**
     * Get the elapsed time since $start to right now, with a transChoice() for translation in plural or singular.
     *
     * @param \DateTime $start The initial time.
     *
     * @return \String
     * @see Symfony\Component\Translation\Translator
     */
    public function timeAgo($start)
    {
        $end = new \DateTime("now");

        $interval = $start->diff($end);

        $formats = array("%Y", "%m", "%W", "%d", "%H", "%i", "%s");
        $translation["singular"] = array(
            "%Y" => "year",
            "%m" => "month",
            "%W" => "week",
            "%d" => "day",
            "%H" => "hour",
            "%i" => "minute",
            "%s" => "second"
        );
        $translation["plural"] = array(
            "%Y" => "years",
            "%m" => "months",
            "%W" => "weeks",
            "%d" => "days",
            "%H" => "hours",
            "%i" => "minutes",
            "%s" => "seconds"
        );

        foreach ($formats as $format) {
            if ($format == "%W") {

                $i = round($interval->format("%d") / 8); //fix for week that does not exist in DataInterval obj
            } else {
                $i = ltrim($interval->format($format), "0");
            }

            if ($i > 0) {
                return $this->container->get("translator")->transChoice(
                    "%count% ".$translation["singular"][$format]." ago|%count% ".$translation["plural"][$format]." ago",
                    $i,
                    array('%count%' => $i),
                    "home"
                );
            }
        }

        return $this->container->get("translator")->transChoice(
            "%count% second ago|%count% seconds ago",
            1,
            array('%count%' => 1),
            "home"
        );
    }

    public function homeLink($link)
    {
        if (!(strpos($link, "http://") === 0 or
            strpos($link, "https://") === 0 or
            strpos($link, "ftp://") === 0 or
            strpos($link, "www.") === 0)
        ) {
            $home = $this->container->get("router")->generate('claro_index').$link;

            $home = str_replace("//", "/", $home);

            return $home;
        }

        return $link;
    }

    public function activeLink($link)
    {
        if ((isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] === $link)
            || (!isset($_SERVER['PATH_INFO']) && $link == '/')) {
            return ' active'; //the white space is nedded
        }

        return '';
    }

    public function compareRoute($link, $return = " class='active'")
    {
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], $link) === 0
            || isset($_SERVER['PATH_INFO']) && strpos($_SERVER['PATH_INFO'], $link) === 0) {
            return $return;
        }

        return '';
    }

    public function autoLink($text)
    {
        $rexProtocol = '(https?://)?';
        $rexDomain   = '((?:[-a-zA-Z0-9]{1,63}\.)+[-a-zA-Z0-9]{2,63}|(?:[0-9]{1,3}\.){3}[0-9]{1,3})';
        $rexPort     = '(:[0-9]{1,5})?';
        $rexPath     = '(/[!$-/0-9:;=@_\':;!a-zA-Z\x7f-\xff]*?)?';
        $rexQuery    = '(\?[!$-/0-9:;=@_\':;!a-zA-Z\x7f-\xff]+?)?';
        $rexFragment = '(#[!$-/0-9:;=@_\':;!a-zA-Z\x7f-\xff]+?)?';

        $text = preg_replace_callback(
            "&\\b$rexProtocol$rexDomain$rexPort$rexPath$rexQuery$rexFragment(?=[?.!,;:\"]?(\s|$))&",
            function ($match) {
                // Prepend http:// if no protocol specified
                $completeUrl = $match[1] ? $match[0] : "http://{$match[0]}";

                return '<a href="' . $completeUrl . '" target="_blank">'
                    . $match[2] . $match[3] . $match[4] . '</a>';
            },
            htmlspecialchars($text)
        );

        return $text;
    }

    /**
     * Check if you come from desktop or workspace.
     */
    public function isDesktop()
    {
        if ($this->container->get('session')->get('isDesktop')) {
            return true;
        }

        return false;
    }

    /**
     * Get the name of the twig extention.
     *
     * @return \String
     */
    public function getName()
    {
        return 'home_extension';
    }

    public function asset_exists($path)
    {
        $webRoot = realpath($this->kernel->getRootDir() . '/../web/');
        $toCheck = realpath($webRoot . '/' . $path);

        // check if the file exists
        if (!is_file($toCheck))
        {
            return false;
        }

        return true;
    }
}

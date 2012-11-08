<?php

/**
 *  UrlLinker - facilitates turning plain text URLs into HTML links.
 *
 *  Author: SÃ¸ren LÃ¸vborg
 *
 *  To the extent possible under law, SÃ¸ren LÃ¸vborg has waived all copyright
 *  and related or neighboring rights to UrlLinker.
 *  http://creativecommons.org/publicdomain/zero/1.0/
 */

namespace Kwi;

class UrlLinker
{
    /**
     * PCRE regular expression for detection of URLs in texts.
     */
    const REGEXP_INLINE_LINK = '(
        (?:^|[\s,.!?])
            (?# Ignore matching braces around the URL)
                (<)?
                    (\[)?
                        (\()?
                            (?# Ignore quoting around the URL)
                            ([\'"]?)
                                (?# Actually match the URL)
                                (?P<match>
                                    (?P<url>[a-z]+://[^\s]*?) |
                                    (?:mailto:)?(?P<mail>[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})
                                )
                            \4
                        (?(3)\))
                    (?(2)\])
                (?(1)>)
            (?# Ignore common punctuation after the URL)
        [.,?!]?(?:\s|$)
    )Sxm';

    const PUNCTUATION = "()'?.!,;: \n";

    private $rexUrlLinker;
    /**
     *  $validTlds is an associative array mapping valid TLDs to the value true.
     *  Since the set of valid TLDs is not static, this array should be updated
     *  from time to time.
     *
     *  List source:  http://data.iana.org/TLD/tlds-alpha-by-domain.txt
     *  Last updated: 2012-09-06
     */
    private $validTlds;

    /*
     *  Regular expression bits used by htmlEscapeAndLinkUrls() to match URLs.
     */
    public function __construct()
    {
        $this->validTlds = array_fill_keys(explode(" ", ".ac .ad .ae .aero .af .ag .ai .al .am .an .ao .aq .ar .arpa .as .asia .at .au .aw .ax .az .ba .bb .bd .be .bf .bg .bh .bi .biz .bj .bm .bn .bo .br .bs .bt .bv .bw .by .bz .ca .cat .cc .cd .cf .cg .ch .ci .ck .cl .cm .cn .co .com .coop .cr .cu .cv .cw .cx .cy .cz .de .dj .dk .dm .do .dz .ec .edu .ee .eg .er .es .et .eu .fi .fj .fk .fm .fo .fr .ga .gb .gd .ge .gf .gg .gh .gi .gl .gm .gn .gov .gp .gq .gr .gs .gt .gu .gw .gy .hk .hm .hn .hr .ht .hu .id .ie .il .im .in .info .int .io .iq .ir .is .it .je .jm .jo .jobs .jp .ke .kg .kh .ki .km .kn .kp .kr .kw .ky .kz .la .lb .lc .li .lk .lr .ls .lt .lu .lv .ly .ma .mc .md .me .mg .mh .mil .mk .ml .mm .mn .mo .mobi .mp .mq .mr .ms .mt .mu .museum .mv .mw .mx .my .mz .na .name .nc .ne .net .nf .ng .ni .nl .no .np .nr .nu .nz .om .org .pa .pe .pf .pg .ph .pk .pl .pm .pn .post .pr .pro .ps .pt .pw .py .qa .re .ro .rs .ru .rw .sa .sb .sc .sd .se .sg .sh .si .sj .sk .sl .sm .sn .so .sr .st .su .sv .sx .sy .sz .tc .td .tel .tf .tg .th .tj .tk .tl .tm .tn .to .tp .tr .travel .tt .tv .tw .tz .ua .ug .uk .us .uy .uz .va .vc .ve .vg .vi .vn .vu .wf .ws .xn--0zwm56d .xn--11b5bs3a9aj6g .xn--3e0b707e .xn--45brj9c .xn--80akhbyknj4f .xn--80ao21a .xn--90a3ac .xn--9t4b11yi5a .xn--clchc0ea0b2g2a9gcd .xn--deba0ad .xn--fiqs8s .xn--fiqz9s .xn--fpcrj9c3d .xn--fzc2c9e2c .xn--g6w251d .xn--gecrj9c .xn--h2brj9c .xn--hgbk6aj7f53bba .xn--hlcj6aya9esc7a .xn--j6w193g .xn--jxalpdlp .xn--kgbechtv .xn--kprw13d .xn--kpry57d .xn--lgbbat1ad8j .xn--mgb9awbf .xn--mgbaam7a8h .xn--mgbayh7gpa .xn--mgbbh1a71e .xn--mgbc0a9azcg .xn--mgberp4a5d4ar .xn--o3cw4h .xn--ogbpf8fl .xn--p1ai .xn--pgbs0dh .xn--s9brj9c .xn--wgbh1c .xn--wgbl6a .xn--xkc2al3hye2a .xn--xkc2dl3a5ee0h .xn--yfro4i67o .xn--ygbi2ammx .xn--zckzah .xxx .ye .yt .za .zm .zw"), true);
    }

    /**
     *  Transforms plain text into valid HTML, escaping special characters and
     *  turning URLs into links.
     */
    public function parse($text)
    {
        $html = '';

        $position = 0;
        while (preg_match(self::REGEXP_INLINE_LINK, $text, $match, PREG_OFFSET_CAPTURE, $position)) {
            list($url, $urlPosition) = $match[0];

            $url = rtrim($url, self::PUNCTUATION);

            $len = strlen($url) - strlen(ltrim($url, self::PUNCTUATION));
            $url = ltrim($url, self::PUNCTUATION);
            $urlPosition = $urlPosition + $len;

            // Add the text leading up to the URL.
            $html .= htmlspecialchars(substr($text, $position, $urlPosition - $position));

            // Skip tent mentions with ^
            if (substr($text, $urlPosition-1, 1) == "^") {
                $html    .= htmlspecialchars($url);
                $position = $urlPosition + strlen($url);
                continue;
            }

            $urlParts = array_merge(array(
                'scheme' => '',
                'username' => '',
                'password' => '',
                'port' => '',
                'path' => '',
                'query' => '',
                'fragment' => '',
                'host' => '',
                ), parse_url($url));

            $scheme      = $urlParts['scheme'];
            $username    = $urlParts['username'];
            $password    = $urlParts['password'];
            $domain      = $urlParts['host'];
            $afterDomain = $urlParts['path'] . $urlParts['query'] . $urlParts['fragment']; // everything following the domain
            $port        = $urlParts['port'];
            $path        = $urlParts['path'];

            // Check that the TLD is valid or that $domain is an IP address.
            $tld = strtolower(strrchr($domain, '.'));
            if (preg_match('{^\.[0-9]{1,3}$}', $tld) || isset($this->validTlds[$tld])) {
                // Do not permit implicit scheme if a password is specified, as
                // this causes too many errors (e.g. "my email:foo@example.org").
                if (!$scheme && $password) {
                    $html .= htmlspecialchars($username);

                    // Continue text parsing at the ':' following the "username".
                    $position = $urlPosition + strlen($username);
                    continue;
                }

                if (!$scheme && $username && !$password && !$afterDomain) {
                    // Looks like an email address.
                    $completeUrl = "mailto:$url";
                    $linkText = $url;
                } else {
                    // Prepend http:// if no scheme is specified
                    $completeUrl = $scheme ? $url : "http://$url";
                    $linkText = "$domain$port$path";
                }

                $linkHtml = '<a href="' . htmlspecialchars($completeUrl) . '">'
                    . htmlspecialchars($linkText)
                    . '</a>';

                // Cheap e-mail obfuscation to trick the dumbest mail harvesters.
                $linkHtml = str_replace('@', '&#64;', $linkHtml);

                // Add the hyperlink.
                $html .= $linkHtml;
            } else {
                // Not a valid URL.
                $html .= htmlspecialchars($url);
            }

            // Continue text parsing from after the URL.
            $position = $urlPosition + strlen($url);
        }

        // Add the remainder of the text.
        $html .= htmlspecialchars(substr($text, $position));

        return $html;
    }
}


<?php
/**
 * Adversarial test corpus for the SVG upload screen.
 *
 *   php tests/svg/run.php
 *
 * Exits 0 only if every bypass attempt is rejected AND every legitimate logo is
 * accepted. Run by CI on every push.
 *
 * The MUST-REJECT cases are the ones that defeated the previous implementation,
 * which scanned the raw upload bytes with a regex blocklist. Raw bytes are not
 * the document: XML identity is (namespace, local name) rather than spelling,
 * character references are resolved by the parser, CDATA lets text masquerade
 * as markup, and an event handler can hide in an attribute VALUE. Each case
 * below is one of those, so a regression toward byte-scanning fails loudly.
 *
 * The MUST-ACCEPT cases are shapes real editors emit. They exist because the
 * cheap way to pass the reject list is to refuse everything, and a logo
 * uploader that rejects Inkscape output is useless.
 */

require_once __DIR__ . '/../../interface/web/customizer/lib/svg_guard.inc.php';

$SVG = 'http://www.w3.org/2000/svg';
$XL  = 'http://www.w3.org/1999/xlink';

$reject = array(
  'plain script'             => "<svg xmlns='$SVG'><script>alert(1)</script></svg>",
  'prefixed script (SVG ns)' => "<s:svg xmlns:s='$SVG'><s:script>alert(1)</s:script></s:svg>",
  'foreign-ns script'        => "<svg xmlns='$SVG' xmlns:h='http://www.w3.org/1999/xhtml'><h:script>alert(1)</h:script></svg>",
  'uppercase SCRIPT'         => "<svg xmlns='$SVG'><SCRIPT>alert(1)</SCRIPT></svg>",
  'foreignObject'            => "<svg xmlns='$SVG'><foreignObject><b>x</b></foreignObject></svg>",
  'prefixed foreignObject'   => "<s:svg xmlns:s='$SVG'><s:foreignObject/></s:svg>",
  'CDATA script'             => "<svg xmlns='$SVG'><script><![CDATA[alert(1)]]></script></svg>",
  'onload attr'              => "<svg xmlns='$SVG' onload='alert(1)'/>",
  'ONLOAD uppercase'         => "<svg xmlns='$SVG' ONLOAD='alert(1)'/>",
  'onclick on child'         => "<svg xmlns='$SVG'><rect onclick='alert(1)'/></svg>",
  'entity decl (billion)'    => "<!DOCTYPE svg [<!ENTITY a 'x'>]><svg xmlns='$SVG'/>",
  'external entity (XXE)'    => "<!DOCTYPE svg [<!ENTITY xxe SYSTEM 'file:///etc/passwd'>]><svg xmlns='$SVG'/>",
  'xml-stylesheet PI'        => "<" . "?xml-stylesheet href='http://evil/x.css'?" . "><svg xmlns='$SVG'/>",
  'anchor javascript href'   => "<svg xmlns='$SVG'><a xmlns:xl='$XL' xl:href='javascript:alert(1)'><rect/></a></svg>",
  'charref javascript href'  => "<svg xmlns='$SVG'><image href='&#106;avascript:alert(1)'/></svg>",
  'SMIL set onload'          => "<svg xmlns='$SVG'><set attributeName='onload' to='alert(1)'/></svg>",
  'animate'                  => "<svg xmlns='$SVG'><animate attributeName='x' to='1'/></svg>",
  'iframe'                   => "<svg xmlns='$SVG'><iframe src='http://evil'/></svg>",
  'style @import'            => "<svg xmlns='$SVG'><style>@import url(http://evil/x.css);</style></svg>",
  'style css escape js'      => "<svg xmlns='$SVG'><rect style='background:url(java\\73 cript:alert(1))'/></svg>",
  'style css comment split'  => "<svg xmlns='$SVG'><style>a{background:url(java/**/script:alert(1))}</style></svg>",
  'remote image href'        => "<svg xmlns='$SVG'><image href='http://evil/x.png'/></svg>",
  'remote use href'          => "<svg xmlns='$SVG'><use href='http://evil/x.svg#a'/></svg>",
  'style expression()'       => "<svg xmlns='$SVG'><rect style='width:expression(alert(1))'/></svg>",
  'moz-binding'              => "<svg xmlns='$SVG'><style>a{-moz-binding:url(http://evil/x.xml#a)}</style></svg>",
  'html embed'               => "<svg xmlns='$SVG' xmlns:h='http://www.w3.org/1999/xhtml'><h:embed src='http://evil'/></svg>",
  'not svg root'             => "<html xmlns='http://www.w3.org/1999/xhtml'><body/></html>",
  'malformed xml'            => "<svg xmlns='$SVG'><rect></svg>",
  'wrong root namespace'     => "<svg xmlns='http://example.com/fake'><rect/></svg>",
);

$accept = array(
  'minimal path'      => "<svg xmlns='$SVG' viewBox='0 0 10 10'><path d='M0 0h10v10H0z'/></svg>",
  'no xmlns at all'   => "<svg viewBox='0 0 10 10'><rect width='10' height='10'/></svg>",
  'gradient + defs'   => "<svg xmlns='$SVG'><defs><linearGradient id='g'><stop offset='0' stop-color='#fff'/></linearGradient></defs><rect fill='url(#g)'/></svg>",
  'text + tspan'      => "<svg xmlns='$SVG'><text x='0' y='10'>Acme<tspan>Ltd</tspan></text></svg>",
  'inkscape output'   => "<svg xmlns='$SVG' xmlns:sodipodi='http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd' xmlns:inkscape='http://www.inkscape.org/namespaces/inkscape'><sodipodi:namedview id='nv' inkscape:zoom='1'/><g inkscape:label='Layer 1'><path d='M0 0h4v4H0z'/></g></svg>",
  'doctype no entity' => "<!DOCTYPE svg PUBLIC '-//W3C//DTD SVG 1.1//EN' 'http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd'><svg xmlns='$SVG'><rect/></svg>",
  'embedded png data' => "<svg xmlns='$SVG'><image href='data:image/png;base64,iVBORw0KGgo='/></svg>",
  'style block plain' => "<svg xmlns='$SVG'><style>.a{fill:#0065AB}</style><rect class='a'/></svg>",
  'filter chain'      => "<svg xmlns='$SVG'><filter id='f'><feGaussianBlur stdDeviation='2'/><feOffset dx='1'/></filter><rect filter='url(#f)'/></svg>",
  'rdf metadata'      => "<svg xmlns='$SVG' xmlns:rdf='http://www.w3.org/1999/02/22-rdf-syntax-ns#' xmlns:dc='http://purl.org/dc/elements/1.1/'><metadata><rdf:RDF><dc:title>Logo</dc:title></rdf:RDF></metadata><rect/></svg>",
  'clipPath + mask'   => "<svg xmlns='$SVG'><clipPath id='c'><circle r='5'/></clipPath><mask id='m'><rect/></mask><g clip-path='url(#c)' mask='url(#m)'><rect/></g></svg>",
  'same-doc use'      => "<svg xmlns='$SVG'><defs><symbol id='s'><rect/></symbol></defs><use href='#s'/></svg>",
);

if(!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
    fwrite(STDERR, "ext/dom is required to run this corpus (and to screen SVG at all).\n");
    exit(2);
}

$fail = 0;
echo "MUST REJECT — a pass here is a bypass\n";
foreach($reject as $name => $svg) {
    if(customizer_svg_ok($svg)) { printf("  BYPASS   %s\n", $name); $fail++; }
    else                        printf("  blocked  %s\n", $name);
}
echo "\nMUST ACCEPT — a block here breaks real logos\n";
foreach($accept as $name => $svg) {
    if(!customizer_svg_ok($svg)) { printf("  REJECTED %s\n", $name); $fail++; }
    else                         printf("  accepted %s\n", $name);
}

$total = count($reject) + count($accept);
printf("\n%d cases, %d bypass attempts, %d real logos — %d failure(s)\n",
    $total, count($reject), count($accept), $fail);
exit($fail > 0 ? 1 : 0);

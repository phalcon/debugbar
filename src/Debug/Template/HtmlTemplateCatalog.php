<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Debug\Template;

use Phalcon\Debug\Contracts\TemplateCatalog;

/**
 * Holds the embedded default template strings for the HTML exception renderer.
 */
final class HtmlTemplateCatalog implements TemplateCatalog
{
    /**
     * Returns the embedded default template for the given name.
     *
     * @param string $name
     *
     * @return string
     */
    public function defaultFor(string $name): string
    {
        return match ($name) {
            'cssLink'        => "
    <link href='%uri%%path%'
          rel='stylesheet'
          type='text/css' />",
            'jsLink'         => "
    <script src='%uri%%path%'></script>",
            'version'        => "<a class='version-badge' href='%link%' target='_new'><b>v%version%</b></a>",
            'document'       => "<!DOCTYPE html>
<html lang='en' data-theme='light'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>%className%:%escapedMessage%</title>%cssSources%
</head>
<body>
<div class='wrap'>",
            'masthead'       => "
    <div class='masthead'>
        <div class='brand'><img class='logo'"
                . " src='https://assets.phalcon.io/phalcon/images/svg/logo--tablet.svg'"
                . " alt='Phalcon' /><span>Phalcon Debug</span></div>
        <div class='actions-top'>
            <button class='btn' data-action='copy-trace'>Copy trace</button>
            <button class='btn' data-action='toggle-theme' title='Toggle theme'>Theme</button>
            %version%
        </div>
    </div>",
            'errorMain'      => "
    <div class='error-card'>
        <span class='error-type'>%className%</span>
        <h1 class='error-message'>%escapedMessage%</h1>
        <div class='meta'>
            <span class='item'><code>%file%</code> : <code>%line%</code></span>
            <span class='sep'>|</span><span class='item'>PHP <code>%phpVersion%</code></span>
        </div>
    </div>",
            'tabs'           => "
    <div class='tabs' role='tablist'>
        <button class='tab is-active' data-tab='backtrace'>Backtrace "
                . "<span class='count'>%backtraceCount%</span></button>
        <button class='tab' data-tab='request'>Request <span class='count'>%requestCount%</span></button>
        <button class='tab' data-tab='server'>Server <span class='count'>%serverCount%</span></button>
        <button class='tab' data-tab='files'>Included Files <span class='count'>%filesCount%</span></button>
        <button class='tab' data-tab='memory'>Memory</button>%variablesTab%
    </div>",
            'variablesTab'   => "
        <button class='tab' data-tab='variables'>Variables <span class='count'>%variablesCount%</span></button>",
            'backtracePanel' => "
    <div class='panel is-active' id='backtrace'>
        <div class='bt-tools'>
            <button class='btn' data-action='expand-all'>Expand all</button>
            <button class='btn' data-action='collapse-all'>Collapse all</button>
        </div>",
            'panelOpen'      => "
    <div class='panel' id='%id%'>",
            'panelClose'     => "
    </div>",
            'wrapClose'      => "
</div>",
            'documentClose'  => "
</body>
</html>",
            'frameOpen'      => "
        <details class='frame %appClass%'%open%>
            <summary><div class='frame-head'>
                <span class='frame-num'>#%num%</span>
                <span class='frame-call'>%signature%</span>%appTag%
                <span class='chev'>&#9656;</span>
            </div></summary>",
            'appTag'         => "<span class='tag-app'>app</span>",
            'frameFile'      => "
            <div class='frame-file' data-file='%file%' data-line='%line%'>
                <span class='path'><b>%file%</b> : %line%</span>
            </div>",
            'frameClose'     => "
        </details>",
            'codeOpen'       => "
            <div class='code'><table>",
            'codeRow'        => "<tr%hlClass%><td class='ln'>%num%</td><td class='src'>%src%</td></tr>",
            'codeClose'      => "</table></div>",
            'link'           => "<a href='%url%' target='_new'>%name%</a>",
            'tableOpen'      => "<table class='grid'><thead><tr><th>%headerOne%</th><th>%headerTwo%</th></tr>"
                . "</thead><tbody>",
            'gridRow'        => "<tr><td class='k'>%key%</td><td class='v'>%value%</td></tr>",
            'tableClose'     => "</tbody></table>",
            'memory'         => "
        <div class='stats'>
            <div class='stat'><div class='label'>Memory usage (real)</div>"
                . "<div class='value'>%memory% <small>MB</small></div></div>
            <div class='stat'><div class='label'>Peak usage</div>"
                . "<div class='value'>%peak% <small>MB</small></div></div>
        </div>",
            default          => '',
        };
    }
}

<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The icon tile of each app on the Connection screen. Claude, Cursor and
 * Gemini use their marks (path data from Simple Icons 16.37.0, CC0; the
 * marks are trademarks of their owners and only say which app a card is
 * for). ChatGPT, VS Code and Antigravity use neutral dashicons until their
 * brand terms are checked; Antigravity carries the Gemini mark as a badge.
 */
final class App_Icons
{
    private const CLAUDE = 'm4.7144 15.9555 4.7174-2.6471.079-.2307-.079-.1275h-.2307l-.7893-.0486-2.6956-.0729-2.3375-.0971-2.2646-.1214-.5707-.1215-.5343-.7042.0546-.3522.4797-.3218.686.0608 1.5179.1032 2.2767.1578 1.6514.0972 2.4468.255h.3886l.0546-.1579-.1336-.0971-.1032-.0972L6.973 9.8356l-2.55-1.6879-1.3356-.9714-.7225-.4918-.3643-.4614-.1578-1.0078.6557-.7225.8803.0607.2246.0607.8925.686 1.9064 1.4754 2.4893 1.8336.3643.3035.1457-.1032.0182-.0728-.164-.2733-1.3539-2.4467-1.445-2.4893-.6435-1.032-.17-.6194c-.0607-.255-.1032-.4674-.1032-.7285L6.287.1335 6.6997 0l.9957.1336.419.3642.6192 1.4147 1.0018 2.2282 1.5543 3.0296.4553.8985.2429.8318.091.255h.1579v-.1457l.1275-1.706.2368-2.0947.2307-2.6957.0789-.7589.3764-.9107.7468-.4918.5828.2793.4797.686-.0668.4433-.2853 1.8517-.5586 2.9021-.3643 1.9429h.2125l.2429-.2429.9835-1.3053 1.6514-2.0643.7286-.8196.85-.9046.5464-.4311h1.0321l.759 1.1293-.34 1.1657-1.0625 1.3478-.8804 1.1414-1.2628 1.7-.7893 1.36.0729.1093.1882-.0183 2.8535-.607 1.5421-.2794 1.8396-.3157.8318.3886.091.3946-.3278.8075-1.967.4857-2.3072.4614-3.4364.8136-.0425.0304.0486.0607 1.5482.1457.6618.0364h1.621l3.0175.2247.7892.522.4736.6376-.079.4857-1.2142.6193-1.6393-.3886-3.825-.9107-1.3113-.3279h-.1822v.1093l1.0929 1.0686 2.0035 1.8092 2.5075 2.3314.1275.5768-.3218.4554-.34-.0486-2.2039-1.6575-.85-.7468-1.9246-1.621h-.1275v.17l.4432.6496 2.3436 3.5214.1214 1.0807-.17.3521-.6071.2125-.6679-.1214-1.3721-1.9246L14.38 17.959l-1.1414-1.9428-.1397.079-.674 7.2552-.3156.3703-.7286.2793-.6071-.4614-.3218-.7468.3218-1.4753.3886-1.9246.3157-1.53.2853-1.9004.17-.6314-.0121-.0425-.1397.0182-1.4328 1.9672-2.1796 2.9446-1.7243 1.8456-.4128.164-.7164-.3704.0667-.6618.4008-.5889 2.386-3.0357 1.4389-1.882.929-1.0868-.0062-.1579h-.0546l-6.3385 4.1164-1.1293.1457-.4857-.4554.0608-.7467.2307-.2429 1.9064-1.3114Z';

    private const CURSOR = 'M11.503.131 1.891 5.678a.84.84 0 0 0-.42.726v11.188c0 .3.162.575.42.724l9.609 5.55a1 1 0 0 0 .998 0l9.61-5.55a.84.84 0 0 0 .42-.724V6.404a.84.84 0 0 0-.42-.726L12.497.131a1.01 1.01 0 0 0-.996 0M2.657 6.338h18.55c.263 0 .43.287.297.515L12.23 22.918c-.062.107-.229.064-.229-.06V12.335a.59.59 0 0 0-.295-.51l-9.11-5.257c-.109-.063-.064-.23.061-.23';

    /** The WPMCP mark (assets/wpmcp-icon.svg), inline because only src/ ships in every flavor. */
    private const MARK = '<svg xmlns="http://www.w3.org/2000/svg" class="wpmcp-mark-svg" viewBox="0 0 256 256" aria-hidden="true" focusable="false"><rect width="256" height="256" fill="#0A0E14"/><svg x="36" y="36" width="184" height="184" viewBox="261.5 259 490 490"><path d="M512 740 A228 228 0 0 1 488 284" fill="none" stroke="#22D3EE" stroke-width="13"/><path d="M486 261 L551 298 L486 337 Z" fill="#22D3EE"/><path d="M401.45859375 588.0H436.4859375L450.7265625 529.07421875C453.82734375 516.22265625 456.81328125 498.72265625 459.79921875 482.58984375C462.78515625 498.5859375 465.77109375 516.0859375 468.98671875 529.07421875L483.22734375 588.0H518.2546875L556.3828125 435.1484375H519.74765625L508.6078125 491.61328125C505.27734375 508.9765625 501.7171875 528.25390625 498.38671875 550.8125C494.71171875 528.1171875 490.57734374999995 508.83984375 487.36171875 491.61328125L475.87734375 435.1484375H444.18046875L432.46640625 491.61328125C429.02109375 508.9765625 424.88671875 528.390625 421.3265625 550.67578125C417.88125 527.98046875 413.9765625 508.703125 410.87578125 491.61328125L399.7359375 435.1484375H363.56015625ZM570.64296875 645.1484375H605.09609375V563.52734375H606.1296874999999C610.953125 575.83203125 621.51875 590.59765625 642.53515625 590.59765625C672.1648437499999 590.59765625 694.7890625 562.70703125 694.7890625 511.84765625C694.7890625 459.62109375 671.24609375 433.234375 642.76484375 433.234375C620.94453125 433.234375 610.83828125 448.68359375 606.1296874999999 460.8515625H604.521875V435.1484375H570.64296875ZM631.9695312499999 557.6484375C614.3984375 557.6484375 604.2921875 539.328125 604.2921875 511.7109375C604.2921875 483.95703125 614.28359375 466.046875 631.9695312499999 466.046875C650.0 466.046875 659.5320312499999 484.9140625 659.5320312499999 511.7109375C659.5320312499999 538.64453125 649.8851562499999 557.6484375 631.9695312499999 557.6484375Z" fill="#E9EEF5"/><rect x="680" y="610" width="68" height="20" fill="#22D3EE"/></svg></svg>';

    private const GEMINI = 'M11.04 19.32Q12 21.51 12 24q0-2.49.93-4.68.96-2.19 2.58-3.81t3.81-2.55Q21.51 12 24 12q-2.49 0-4.68-.93a12.3 12.3 0 0 1-3.81-2.58 12.3 12.3 0 0 1-2.58-3.81Q12 2.49 12 0q0 2.49-.96 4.68-.93 2.19-2.55 3.81a12.3 12.3 0 0 1-3.81 2.58Q2.49 12 0 12q2.49 0 4.68.96 2.19.93 3.81 2.55t2.55 3.81';

    /** The tile for an app: a mark or a dashicon, with a badge where one tells two apps apart. */
    public function tile(Client_App $app): string
    {
        [$tint, $icon, $badge] = match ($app) {
            Client_App::ClaudeDesktop => ['t-claude', $this->svg(self::CLAUDE, '#D97757'), '<span class="dashicons dashicons-desktop" aria-hidden="true"></span>'],
            Client_App::ClaudeCode => ['t-claude', $this->svg(self::CLAUDE, '#D97757'), '&gt;_'],
            Client_App::Chatgpt => ['t-plain', '<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>', ''],
            Client_App::Cursor => ['t-plain', $this->svg(self::CURSOR, '#000000'), ''],
            Client_App::Antigravity => ['t-plain', '<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>', $this->svg(self::GEMINI, '#8E75B2')],
            Client_App::Other => ['t-plain', '<span class="dashicons dashicons-networking" aria-hidden="true"></span>', ''],
        };

        return '<span class="wpmcp-app__icon ' . $tint . '" aria-hidden="true">' . $icon
            . ('' === $badge ? '' : '<span class="wpmcp-app__badge">' . $badge . '</span>') . '</span>';
    }

    /**
     * What wp_kses() keeps of a tile.
     *
     * @return array<string, array<string, bool>>
     */
    public function allowed_html(): array
    {
        return [
            'span' => [ 'class' => true, 'aria-hidden' => true ],
            'svg'  => [ 'viewbox' => true, 'class' => true, 'aria-hidden' => true, 'focusable' => true, 'xmlns' => true ],
            'path' => [ 'd' => true, 'fill' => true ],
        ];
    }

    /** The WPMCP mark for the screen header. */
    public function mark(): string
    {
        return self::MARK;
    }

    /**
     * What wp_kses() keeps of the mark.
     *
     * @return array<string, array<string, bool>>
     */
    public function allowed_mark_html(): array
    {
        return [
            'svg'  => [ 'xmlns' => true, 'viewbox' => true, 'class' => true, 'aria-hidden' => true, 'focusable' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true ],
            'rect' => [ 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'fill' => true ],
            'path' => [ 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ],
        ];
    }

    private function svg(string $path, string $fill): string
    {
        return '<svg class="wpmcp-mark-svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><path fill="' . $fill . '" d="' . $path . '"/></svg>';
    }
}

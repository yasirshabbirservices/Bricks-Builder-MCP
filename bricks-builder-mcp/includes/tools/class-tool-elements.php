<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static catalog of all Bricks Builder elements.
 * No database access — purely reference data for the AI.
 */
class Tool_Elements extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_get_elements',
				'description' => 'Get the complete Bricks Builder element catalog. Returns all available elements with descriptions, key settings, and example JSON. Use this before building any page to understand what elements are available and how to configure them.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'category' => [
							'type'        => 'string',
							'description' => 'Filter by category: layout | basic | general | media | post | query | form | filter | woocommerce | navigation | misc',
						],
						'search' => [
							'type'        => 'string',
							'description' => 'Search elements by name or keyword',
						],
					],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		if ( $name !== 'bricks_get_elements' ) {
			return $this->err( 'Unknown tool: ' . $name );
		}

		$catalog  = self::get_catalog();
		$category = strtolower( $this->str_arg( $args, 'category' ) );
		$search   = strtolower( $this->str_arg( $args, 'search' ) );

		if ( $category ) {
			$catalog = array_filter( $catalog, fn( $el ) => ( $el['category'] ?? '' ) === $category );
		}

		if ( $search ) {
			$catalog = array_filter( $catalog, function ( $el ) use ( $search ) {
				$haystack = strtolower( $el['name'] . ' ' . $el['description'] . ' ' . implode( ' ', $el['keywords'] ?? [] ) );
				return str_contains( $haystack, $search );
			} );
		}

		return [
			'elements' => array_values( $catalog ),
			'total'    => count( $catalog ),
			'note'     => 'Use element "name" field in your elements array. Nestable elements can have children. Set parent/children consistently.',
		];
	}

	// -------------------------------------------------------------------------
	// Static element catalog
	// -------------------------------------------------------------------------

	public static function get_catalog(): array {
		return [
			// =================================================================
			// LAYOUT
			// =================================================================
			[
				'name'        => 'container',
				'category'    => 'layout',
				'nestable'    => true,
				'description' => 'Primary layout building block. Flexbox container supporting row/column layouts, grid, query loops. The most-used Bricks element — nearly every layout starts with containers.',
				'keywords'    => [ 'flex', 'grid', 'layout', 'wrapper', 'row', 'column', 'section' ],
				'key_settings'=> [
					'tag'            => 'div | section | article | main | aside | header | footer | nav (HTML tag)',
					'direction'      => 'row | column (flex direction)',
					'justifyContent' => 'flex-start | center | flex-end | space-between | space-around | space-evenly',
					'alignItems'     => 'flex-start | center | flex-end | stretch | baseline',
					'flexWrap'       => 'nowrap | wrap | wrap-reverse',
					'gap'            => '{ row: "20px", column: "20px" }',
					'width'          => 'CSS value (e.g. "100%", "1200px")',
					'maxWidth'       => 'CSS value',
					'padding'        => '{ top, right, bottom, left } in px/em/%',
					'margin'         => '{ top, right, bottom, left }',
					'background'     => '{ color: { raw: "#hex" } }',
					'_cssGlobalClasses' => '["class-id-1", "class-id-2"]',
					'query'          => 'Query loop config — { useQueryEditor: true, postType: "post", ... }',
				],
				'example'     => [
					'id' => 'con001', 'name' => 'container', 'parent' => '0', 'children' => [],
					'settings' => [ 'tag' => 'section', 'direction' => 'column', 'alignItems' => 'center', 'padding' => [ 'top' => '60px', 'bottom' => '60px' ] ],
				],
			],
			[
				'name'        => 'section',
				'category'    => 'layout',
				'nestable'    => true,
				'description' => 'Semantic <section> layout element. Similar to container but always renders as a section tag. Use for major page sections.',
				'keywords'    => [ 'section', 'layout', 'block' ],
				'key_settings'=> [
					'padding'    => '{ top, right, bottom, left }',
					'background' => 'Background color or image',
					'fullHeight' => 'true | false — 100vh height',
				],
				'example'     => [
					'id' => 'sec001', 'name' => 'section', 'parent' => '0', 'children' => [],
					'settings' => [ 'padding' => [ 'top' => '80px', 'bottom' => '80px' ] ],
				],
			],
			[
				'name'        => 'div',
				'category'    => 'layout',
				'nestable'    => true,
				'description' => 'Generic nestable <div> wrapper. Use when you need a non-semantic container for grouping elements.',
				'keywords'    => [ 'div', 'wrapper', 'group' ],
				'key_settings'=> [
					'padding' => 'Spacing',
					'margin'  => 'Spacing',
				],
				'example'     => [
					'id' => 'div001', 'name' => 'div', 'parent' => '0', 'children' => [],
					'settings' => [],
				],
			],
			[
				'name'        => 'block',
				'category'    => 'layout',
				'nestable'    => true,
				'description' => 'Block-level layout container, similar to div. Renders as a div by default.',
				'keywords'    => [ 'block', 'layout' ],
				'key_settings'=> [],
				'example'     => [
					'id' => 'blk001', 'name' => 'block', 'parent' => '0', 'children' => [],
					'settings' => [],
				],
			],
			// =================================================================
			// BASIC CONTENT
			// =================================================================
			[
				'name'        => 'heading',
				'category'    => 'basic',
				'nestable'    => false,
				'description' => 'Heading element (h1–h6). Use for page titles, section headers. Supports dynamic data, custom HTML tags.',
				'keywords'    => [ 'heading', 'title', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'headline' ],
				'key_settings'=> [
					'text'       => 'Heading text content',
					'tag'        => 'h1 | h2 | h3 | h4 | h5 | h6 (default: h2)',
					'link'       => '{ url: "https://...", target: "_blank" }',
					'typography' => '{ fontSize: "2rem", fontWeight: "700", color: { raw: "#111" } }',
					'textAlign'  => 'left | center | right',
				],
				'example'     => [
					'id' => 'h001', 'name' => 'heading', 'parent' => '0', 'children' => [],
					'settings' => [ 'text' => 'Welcome to Our Site', 'tag' => 'h1', 'typography' => [ 'fontSize' => '3rem', 'fontWeight' => '700' ] ],
				],
			],
			[
				'name'        => 'text',
				'category'    => 'basic',
				'nestable'    => false,
				'description' => 'Rich text element with WYSIWYG editor. Supports HTML, bold, italic, links, lists. Best for longer text blocks.',
				'keywords'    => [ 'text', 'paragraph', 'content', 'rich text', 'body' ],
				'key_settings'=> [
					'content'   => 'HTML text content',
					'tag'       => 'p | div | span (default: div)',
					'link'      => 'Wrap entire element in link',
					'textAlign' => 'left | center | right | justify',
					'typography'=> 'Font settings',
				],
				'example'     => [
					'id' => 't001', 'name' => 'text', 'parent' => '0', 'children' => [],
					'settings' => [ 'content' => '<p>This is a paragraph of body text.</p>', 'typography' => [ 'fontSize' => '1.125rem', 'lineHeight' => '1.7' ] ],
				],
			],
			[
				'name'        => 'text-basic',
				'category'    => 'basic',
				'nestable'    => false,
				'description' => 'Simple text element without a rich editor. Good for short single-line text strings with full style control.',
				'keywords'    => [ 'text', 'label', 'simple' ],
				'key_settings'=> [
					'text'      => 'Plain text (supports dynamic tags)',
					'tag'       => 'p | span | div | li | etc.',
					'link'      => 'Optional link',
					'textAlign' => 'left | center | right',
				],
				'example'     => [
					'id' => 'tb001', 'name' => 'text-basic', 'parent' => '0', 'children' => [],
					'settings' => [ 'text' => 'Short label text', 'tag' => 'p' ],
				],
			],
			[
				'name'        => 'button',
				'category'    => 'basic',
				'nestable'    => false,
				'description' => 'Clickable button with optional icon. Supports all link types (URL, page, phone, email, etc.).',
				'keywords'    => [ 'button', 'cta', 'call to action', 'link', 'action' ],
				'key_settings'=> [
					'text'          => 'Button label text',
					'link'          => '{ url: "https://...", target: "_blank", type: "external" }',
					'icon'          => '{ library: "font-awesome-6-brands", name: "fa-arrow-right" }',
					'iconPosition'  => 'left | right',
					'style'         => 'outline | text (leave empty for filled)',
					'size'          => 'sm | md | lg',
					'width'         => 'CSS width or "100%" for full-width',
					'background'    => '{ color: { raw: "#3b82f6" } }',
					'color'         => '{ raw: "#ffffff" }',
					'border'        => 'Border settings',
					'borderRadius'  => 'Border radius',
					'padding'       => 'Button padding',
				],
				'example'     => [
					'id' => 'btn001', 'name' => 'button', 'parent' => '0', 'children' => [],
					'settings' => [
						'text' => 'Get Started',
						'link' => [ 'url' => '/contact', 'type' => 'internal' ],
						'background' => [ 'color' => [ 'raw' => '#3b82f6' ] ],
						'color' => [ 'raw' => '#ffffff' ],
						'padding' => [ 'top' => '14px', 'right' => '28px', 'bottom' => '14px', 'left' => '28px' ],
						'borderRadius' => '8px',
					],
				],
			],
			[
				'name'        => 'image',
				'category'    => 'basic',
				'nestable'    => false,
				'description' => 'Image element. Supports WordPress media library, external URLs, dynamic image sources. Has lightbox, filters, responsive settings.',
				'keywords'    => [ 'image', 'photo', 'picture', 'img', 'media' ],
				'key_settings'=> [
					'image'        => '{ id: 123, url: "https://..." } — WordPress attachment or external URL',
					'size'         => 'thumbnail | medium | large | full | custom (WordPress image size)',
					'link'         => 'Wrap in link',
					'lightbox'     => 'true | false — open in lightbox on click',
					'loading'      => 'lazy | eager',
					'objectFit'    => 'cover | contain | fill | none',
					'height'       => 'CSS height (e.g. "300px")',
					'borderRadius' => 'Border radius',
				],
				'example'     => [
					'id' => 'img001', 'name' => 'image', 'parent' => '0', 'children' => [],
					'settings' => [ 'image' => [ 'url' => 'https://picsum.photos/800/400' ], 'size' => 'large', 'objectFit' => 'cover' ],
				],
			],
			[
				'name'        => 'icon',
				'category'    => 'basic',
				'nestable'    => false,
				'description' => 'SVG icon from Font Awesome, Themify, or custom icon sets.',
				'keywords'    => [ 'icon', 'svg', 'symbol', 'font awesome' ],
				'key_settings'=> [
					'icon'  => '{ library: "themify-icons", name: "ti-star" }',
					'link'  => 'Optional link',
					'color' => 'Icon color',
					'size'  => 'Icon size (CSS value)',
				],
				'example'     => [
					'id' => 'ico001', 'name' => 'icon', 'parent' => '0', 'children' => [],
					'settings' => [ 'icon' => [ 'library' => 'themify-icons', 'name' => 'ti-star' ], 'color' => [ 'raw' => '#f59e0b' ], 'size' => '2rem' ],
				],
			],
			[
				'name'        => 'icon-box',
				'category'    => 'basic',
				'nestable'    => false,
				'description' => 'Icon combined with title and description text. Common for feature/benefit sections.',
				'keywords'    => [ 'icon box', 'feature', 'card', 'service' ],
				'key_settings'=> [
					'icon'        => 'Icon settings',
					'iconPosition'=> 'top | left | right',
					'title'       => 'Box title text',
					'titleTag'    => 'h2 | h3 | h4',
					'content'     => 'Description text',
					'link'        => 'Link for the entire box',
				],
				'example'     => [
					'id' => 'ib001', 'name' => 'icon-box', 'parent' => '0', 'children' => [],
					'settings' => [
						'icon'         => [ 'library' => 'themify-icons', 'name' => 'ti-rocket' ],
						'iconPosition' => 'top',
						'title'        => 'Fast Performance',
						'titleTag'     => 'h3',
						'content'      => 'Our platform is optimized for speed.',
					],
				],
			],
			[
				'name'        => 'divider',
				'category'    => 'basic',
				'nestable'    => false,
				'description' => 'Horizontal divider line. Use to separate sections visually.',
				'keywords'    => [ 'divider', 'separator', 'hr', 'line' ],
				'key_settings'=> [
					'style'  => 'solid | dashed | dotted | double',
					'color'  => 'Line color',
					'width'  => 'Line width (CSS value or %)',
					'height' => 'Line thickness',
				],
				'example'     => [
					'id' => 'div001', 'name' => 'divider', 'parent' => '0', 'children' => [],
					'settings' => [ 'color' => [ 'raw' => '#e5e7eb' ], 'style' => 'solid' ],
				],
			],
			[
				'name'        => 'spacer',
				'category'    => 'basic',
				'nestable'    => false,
				'description' => 'Empty space element. Use to add vertical spacing between elements.',
				'keywords'    => [ 'spacer', 'gap', 'space', 'margin' ],
				'key_settings'=> [
					'height' => 'Spacer height (e.g. "40px")',
				],
				'example'     => [
					'id' => 'sp001', 'name' => 'spacer', 'parent' => '0', 'children' => [],
					'settings' => [ 'height' => [ 'size' => 40, 'unit' => 'px' ] ],
				],
			],
			[
				'name'        => 'list',
				'category'    => 'basic',
				'nestable'    => false,
				'description' => 'Styled list element. Supports icons per item, custom bullets.',
				'keywords'    => [ 'list', 'ul', 'ol', 'items', 'bullet' ],
				'key_settings'=> [
					'items' => '[{ text: "Item 1", icon: {...} }, ...]',
					'style' => 'none | disc | decimal | check (icon-based)',
					'icon'  => 'Default icon for all items',
				],
				'example'     => [
					'id' => 'lst001', 'name' => 'list', 'parent' => '0', 'children' => [],
					'settings' => [ 'items' => [ [ 'text' => 'Feature one' ], [ 'text' => 'Feature two' ], [ 'text' => 'Feature three' ] ] ],
				],
			],
			[
				'name'        => 'video',
				'category'    => 'basic',
				'nestable'    => false,
				'description' => 'Video player. Supports YouTube, Vimeo, self-hosted MP4, and WordPress media library.',
				'keywords'    => [ 'video', 'youtube', 'vimeo', 'media', 'embed' ],
				'key_settings'=> [
					'videoType'   => 'youtube | vimeo | file | url',
					'url'         => 'Video URL (YouTube/Vimeo URL or direct MP4)',
					'autoplay'    => 'true | false',
					'muted'       => 'true | false',
					'controls'    => 'true | false',
					'loop'        => 'true | false',
					'aspectRatio' => '16/9 | 4/3 | 1/1',
				],
				'example'     => [
					'id' => 'vid001', 'name' => 'video', 'parent' => '0', 'children' => [],
					'settings' => [ 'videoType' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'controls' => true ],
				],
			],
			[
				'name'        => 'audio',
				'category'    => 'basic',
				'nestable'    => false,
				'description' => 'HTML5 audio player.',
				'keywords'    => [ 'audio', 'sound', 'music', 'podcast', 'mp3' ],
				'key_settings'=> [
					'url'      => 'Audio file URL',
					'autoplay' => 'true | false',
					'loop'     => 'true | false',
				],
				'example'     => [
					'id' => 'aud001', 'name' => 'audio', 'parent' => '0', 'children' => [],
					'settings' => [ 'url' => 'https://example.com/audio.mp3' ],
				],
			],
			[
				'name'        => 'html',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Raw HTML code block. Output any custom HTML directly on the page.',
				'keywords'    => [ 'html', 'code', 'embed', 'custom', 'raw' ],
				'key_settings'=> [
					'code' => 'Raw HTML code string',
				],
				'example'     => [
					'id' => 'html001', 'name' => 'html', 'parent' => '0', 'children' => [],
					'settings' => [ 'code' => '<div class="custom-widget">Hello</div>' ],
				],
			],
			[
				'name'        => 'code',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Code snippet with syntax highlighting. Good for developer documentation.',
				'keywords'    => [ 'code', 'syntax', 'snippet', 'programming' ],
				'key_settings'=> [
					'code'     => 'Code string',
					'language' => 'javascript | php | css | html | python | etc.',
				],
				'example'     => [
					'id' => 'code001', 'name' => 'code', 'parent' => '0', 'children' => [],
					'settings' => [ 'code' => 'const greeting = "Hello, World!";', 'language' => 'javascript' ],
				],
			],
			[
				'name'        => 'shortcode',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'WordPress shortcode renderer. Execute any registered shortcode.',
				'keywords'    => [ 'shortcode', 'plugin', 'embed' ],
				'key_settings'=> [
					'shortcode' => 'WordPress shortcode string (e.g. "[contact-form-7 id=123]")',
				],
				'example'     => [
					'id' => 'sc001', 'name' => 'shortcode', 'parent' => '0', 'children' => [],
					'settings' => [ 'shortcode' => '[contact-form-7 id="123"]' ],
				],
			],
			[
				'name'        => 'alert',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Alert/notice box in info, success, warning, or danger styles.',
				'keywords'    => [ 'alert', 'notice', 'warning', 'info', 'message', 'notification' ],
				'key_settings'=> [
					'type'    => 'info | success | warning | danger',
					'title'   => 'Alert title',
					'content' => 'Alert message text',
					'icon'    => 'Optional icon',
				],
				'example'     => [
					'id' => 'alt001', 'name' => 'alert', 'parent' => '0', 'children' => [],
					'settings' => [ 'type' => 'info', 'title' => 'Note', 'content' => 'Please read the instructions carefully.' ],
				],
			],
			// =================================================================
			// NAVIGATION
			// =================================================================
			[
				'name'        => 'nav-menu',
				'category'    => 'navigation',
				'nestable'    => false,
				'description' => 'WordPress navigation menu. Renders a registered WP menu. Essential for headers/footers.',
				'keywords'    => [ 'nav', 'menu', 'navigation', 'header menu', 'links' ],
				'key_settings'=> [
					'menu'         => 'WordPress menu ID or slug',
					'direction'    => 'horizontal | vertical',
					'mobileMenu'   => 'true | false — enable hamburger mobile menu',
					'mobileBreakpoint' => 'Breakpoint key (e.g. "mobile")',
				],
				'example'     => [
					'id' => 'nav001', 'name' => 'nav-menu', 'parent' => '0', 'children' => [],
					'settings' => [ 'direction' => 'horizontal', 'mobileMenu' => true ],
				],
			],
			[
				'name'        => 'logo',
				'category'    => 'navigation',
				'nestable'    => false,
				'description' => 'Site logo element. Displays the WordPress custom logo or a custom image.',
				'keywords'    => [ 'logo', 'brand', 'site logo', 'header' ],
				'key_settings'=> [
					'type'   => 'site-logo | custom-image | text',
					'image'  => 'Custom image (if type = custom-image)',
					'text'   => 'Custom text (if type = text)',
					'link'   => 'Logo link (defaults to homepage)',
					'height' => 'Logo height',
				],
				'example'     => [
					'id' => 'logo001', 'name' => 'logo', 'parent' => '0', 'children' => [],
					'settings' => [ 'type' => 'site-logo', 'height' => [ 'size' => 40, 'unit' => 'px' ] ],
				],
			],
			[
				'name'        => 'breadcrumbs',
				'category'    => 'navigation',
				'nestable'    => false,
				'description' => 'Breadcrumb navigation trail.',
				'keywords'    => [ 'breadcrumb', 'navigation', 'trail', 'path' ],
				'key_settings'=> [
					'separator' => 'Separator character or icon',
					'homeText'  => 'Home link label',
				],
				'example'     => [
					'id' => 'bc001', 'name' => 'breadcrumbs', 'parent' => '0', 'children' => [],
					'settings' => [ 'separator' => '/', 'homeText' => 'Home' ],
				],
			],
			[
				'name'        => 'search',
				'category'    => 'navigation',
				'nestable'    => false,
				'description' => 'WordPress site search form.',
				'keywords'    => [ 'search', 'form', 'find' ],
				'key_settings'=> [
					'placeholder'  => 'Input placeholder text',
					'buttonText'   => 'Search button label',
					'showIcon'     => 'true | false',
				],
				'example'     => [
					'id' => 'srch001', 'name' => 'search', 'parent' => '0', 'children' => [],
					'settings' => [ 'placeholder' => 'Search...', 'buttonText' => 'Search' ],
				],
			],
			[
				'name'        => 'back-to-top',
				'category'    => 'navigation',
				'nestable'    => false,
				'description' => 'Floating back-to-top scroll button.',
				'keywords'    => [ 'back to top', 'scroll', 'up', 'button' ],
				'key_settings'=> [
					'icon'     => 'Icon for the button',
					'position' => 'bottom-right | bottom-left | bottom-center',
				],
				'example'     => [
					'id' => 'btt001', 'name' => 'back-to-top', 'parent' => '0', 'children' => [],
					'settings' => [ 'position' => 'bottom-right' ],
				],
			],
			// =================================================================
			// INTERACTIVE / CONTENT BLOCKS
			// =================================================================
			[
				'name'        => 'accordion',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Accordion with collapsible FAQ items. Items defined as array with title and content.',
				'keywords'    => [ 'accordion', 'faq', 'collapse', 'expand', 'toggle' ],
				'key_settings'=> [
					'items'    => '[{ title: "Q?", content: "<p>Answer</p>" }, ...]',
					'openFirst'=> 'true | false — open first item by default',
					'icon'     => 'Toggle icon (+ / chevron)',
				],
				'example'     => [
					'id' => 'acc001', 'name' => 'accordion', 'parent' => '0', 'children' => [],
					'settings' => [
						'openFirst' => true,
						'items'     => [
							[ 'title' => 'What is your return policy?', 'content' => '<p>We offer 30-day returns.</p>' ],
							[ 'title' => 'How long does shipping take?', 'content' => '<p>3-5 business days.</p>' ],
						],
					],
				],
			],
			[
				'name'        => 'accordion-nested',
				'category'    => 'general',
				'nestable'    => true,
				'description' => 'Nestable accordion — each panel can contain any Bricks elements. More flexible than regular accordion.',
				'keywords'    => [ 'accordion', 'nested', 'faq', 'panels' ],
				'key_settings'=> [
					'openFirst' => 'true | false',
					'icon'      => 'Toggle icon',
				],
				'example'     => [
					'id' => 'accn001', 'name' => 'accordion-nested', 'parent' => '0', 'children' => [],
					'settings' => [ 'openFirst' => true ],
				],
			],
			[
				'name'        => 'tabs',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Tabbed content with title/content pairs.',
				'keywords'    => [ 'tabs', 'tabbed', 'panels', 'switch' ],
				'key_settings'=> [
					'items'       => '[{ title: "Tab 1", content: "<p>Content</p>" }, ...]',
					'direction'   => 'horizontal | vertical',
					'layout'      => 'top | bottom | left | right',
				],
				'example'     => [
					'id' => 'tab001', 'name' => 'tabs', 'parent' => '0', 'children' => [],
					'settings' => [
						'layout' => 'top',
						'items'  => [
							[ 'title' => 'Overview', 'content' => '<p>Overview content.</p>' ],
							[ 'title' => 'Details', 'content' => '<p>Detailed content.</p>' ],
						],
					],
				],
			],
			[
				'name'        => 'tabs-nested',
				'category'    => 'general',
				'nestable'    => true,
				'description' => 'Nestable tabs — each tab panel can contain any Bricks elements.',
				'keywords'    => [ 'tabs', 'nested', 'tabbed', 'panels' ],
				'key_settings'=> [
					'layout' => 'top | bottom | left | right',
				],
				'example'     => [
					'id' => 'tabn001', 'name' => 'tabs-nested', 'parent' => '0', 'children' => [],
					'settings' => [ 'layout' => 'top' ],
				],
			],
			[
				'name'        => 'slider-nested',
				'category'    => 'general',
				'nestable'    => true,
				'description' => 'Nestable slider/carousel — each slide can contain any Bricks elements. Great for hero sliders.',
				'keywords'    => [ 'slider', 'carousel', 'slideshow', 'hero', 'nested' ],
				'key_settings'=> [
					'autoplay'   => 'true | false',
					'arrows'     => 'true | false — navigation arrows',
					'dots'       => 'true | false — pagination dots',
					'speed'      => 'Transition speed in ms',
					'interval'   => 'Autoplay interval in ms',
				],
				'example'     => [
					'id' => 'sln001', 'name' => 'slider-nested', 'parent' => '0', 'children' => [],
					'settings' => [ 'autoplay' => true, 'arrows' => true, 'dots' => true, 'interval' => 5000 ],
				],
			],
			[
				'name'        => 'carousel',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Image/content carousel. Good for testimonials, logos, product images.',
				'keywords'    => [ 'carousel', 'slider', 'swipe', 'gallery' ],
				'key_settings'=> [
					'items'      => '[{ image: {...}, title: "", content: "" }, ...]',
					'perView'    => 'Items visible at once',
					'autoplay'   => 'true | false',
					'arrows'     => 'true | false',
					'dots'       => 'true | false',
				],
				'example'     => [
					'id' => 'car001', 'name' => 'carousel', 'parent' => '0', 'children' => [],
					'settings' => [ 'perView' => 3, 'autoplay' => false, 'arrows' => true ],
				],
			],
			// =================================================================
			// SOCIAL / MEDIA
			// =================================================================
			[
				'name'        => 'social-icons',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Social media icon links (Facebook, Twitter/X, Instagram, LinkedIn, YouTube, etc.).',
				'keywords'    => [ 'social', 'icons', 'facebook', 'twitter', 'instagram', 'linkedin' ],
				'key_settings'=> [
					'items'      => '[{ type: "facebook", url: "https://..." }, { type: "instagram", url: "..." }]',
					'size'       => 'Icon size',
					'style'      => 'default | rounded | square | minimal',
					'color'      => 'Icon color style (brand | custom)',
				],
				'example'     => [
					'id' => 'si001', 'name' => 'social-icons', 'parent' => '0', 'children' => [],
					'settings' => [
						'items' => [
							[ 'type' => 'facebook', 'url' => 'https://facebook.com/yourpage' ],
							[ 'type' => 'instagram', 'url' => 'https://instagram.com/yourhandle' ],
						],
						'style' => 'rounded',
					],
				],
			],
			[
				'name'        => 'image-gallery',
				'category'    => 'media',
				'nestable'    => false,
				'description' => 'Image gallery with lightbox support. Supports grid and masonry layouts.',
				'keywords'    => [ 'gallery', 'images', 'grid', 'photos', 'portfolio', 'lightbox' ],
				'key_settings'=> [
					'images'   => '[{ id: 123, url: "..." }, ...]',
					'layout'   => 'grid | masonry',
					'columns'  => 'Number of columns (2 | 3 | 4)',
					'lightbox' => 'true | false',
					'gap'      => 'Gap between images',
				],
				'example'     => [
					'id' => 'ig001', 'name' => 'image-gallery', 'parent' => '0', 'children' => [],
					'settings' => [ 'layout' => 'grid', 'columns' => '3', 'lightbox' => true, 'gap' => [ 'size' => 16, 'unit' => 'px' ] ],
				],
			],
			// =================================================================
			// DISPLAY ELEMENTS
			// =================================================================
			[
				'name'        => 'testimonials',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Customer testimonial cards with name, role, content, and optional image/rating.',
				'keywords'    => [ 'testimonials', 'reviews', 'quotes', 'customers' ],
				'key_settings'=> [
					'items'  => '[{ name: "Jane", role: "CEO", content: "Great product!", image: {...} }]',
					'style'  => 'card | quote | minimal',
					'layout' => 'grid | carousel',
				],
				'example'     => [
					'id' => 'tes001', 'name' => 'testimonials', 'parent' => '0', 'children' => [],
					'settings' => [
						'layout' => 'grid',
						'items'  => [
							[ 'name' => 'John Smith', 'role' => 'Marketing Director', 'content' => 'Absolutely amazing service!' ],
							[ 'name' => 'Jane Doe', 'role' => 'CEO', 'content' => 'Transformed our business completely.' ],
						],
					],
				],
			],
			[
				'name'        => 'pricing-tables',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Pricing plan comparison table with features list, price, and CTA button.',
				'keywords'    => [ 'pricing', 'plans', 'packages', 'subscription', 'price table' ],
				'key_settings'=> [
					'items' => '[{ title: "Pro", price: "$29", period: "/mo", features: [...], button: { text, link } }]',
				],
				'example'     => [
					'id' => 'pt001', 'name' => 'pricing-tables', 'parent' => '0', 'children' => [],
					'settings' => [
						'items' => [
							[
								'title'    => 'Starter',
								'price'    => '$9',
								'period'   => '/mo',
								'features' => [ '5 projects', '10 GB storage', 'Email support' ],
								'button'   => [ 'text' => 'Get Started', 'link' => [ 'url' => '/signup' ] ],
							],
						],
					],
				],
			],
			[
				'name'        => 'team-members',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Team member cards with photo, name, role, bio, and social links.',
				'keywords'    => [ 'team', 'staff', 'people', 'members', 'about' ],
				'key_settings'=> [
					'items'   => '[{ name: "Alice", role: "Dev", image: {...}, bio: "...", social: [...] }]',
					'columns' => 'Items per row',
				],
				'example'     => [
					'id' => 'tm001', 'name' => 'team-members', 'parent' => '0', 'children' => [],
					'settings' => [
						'items' => [ [ 'name' => 'Alice Johnson', 'role' => 'Lead Developer', 'bio' => 'Full-stack engineer with 10 years experience.' ] ],
					],
				],
			],
			[
				'name'        => 'counter',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Animated number counter. Counts up to a target number on scroll. Good for stats sections.',
				'keywords'    => [ 'counter', 'stats', 'number', 'animate', 'count up' ],
				'key_settings'=> [
					'start'   => 'Starting number (default 0)',
					'end'     => 'Target number',
					'prefix'  => 'Text before number (e.g. "$")',
					'suffix'  => 'Text after number (e.g. "+")',
					'duration'=> 'Animation duration in ms',
					'title'   => 'Label below number',
				],
				'example'     => [
					'id' => 'cnt001', 'name' => 'counter', 'parent' => '0', 'children' => [],
					'settings' => [ 'end' => 5000, 'suffix' => '+', 'title' => 'Happy Customers', 'duration' => 2000 ],
				],
			],
			[
				'name'        => 'countdown',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Countdown timer to a specific date. Good for launches, sales, events.',
				'keywords'    => [ 'countdown', 'timer', 'launch', 'deadline', 'event' ],
				'key_settings'=> [
					'date'   => 'Target date (YYYY-MM-DD HH:MM:SS)',
					'labels' => 'true | false — show Days/Hours/Minutes/Seconds labels',
				],
				'example'     => [
					'id' => 'cd001', 'name' => 'countdown', 'parent' => '0', 'children' => [],
					'settings' => [ 'date' => '2026-12-31 00:00:00', 'labels' => true ],
				],
			],
			[
				'name'        => 'progress-bar',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Animated progress/skill bar. Shows percentage completion with label.',
				'keywords'    => [ 'progress', 'skill', 'bar', 'percentage' ],
				'key_settings'=> [
					'items' => '[{ label: "Design", value: 90 }, { label: "Development", value: 85 }]',
				],
				'example'     => [
					'id' => 'pb001', 'name' => 'progress-bar', 'parent' => '0', 'children' => [],
					'settings' => [ 'items' => [ [ 'label' => 'Design', 'value' => 90 ], [ 'label' => 'Development', 'value' => 80 ] ] ],
				],
			],
			[
				'name'        => 'form',
				'category'    => 'form',
				'nestable'    => false,
				'description' => 'Contact/lead generation form builder. Supports text, email, textarea, select, checkbox, radio, file upload fields. Built-in SMTP/email sending.',
				'keywords'    => [ 'form', 'contact', 'email', 'input', 'fields', 'submit' ],
				'key_settings'=> [
					'fields'      => '[{ type: "email", label: "Email", required: true, width: "100" }, ...]',
					'actions'     => '[{ type: "email", to: "admin@site.com", subject: "New message" }]',
					'submitText'  => 'Submit button text',
					'successMsg'  => 'Success message after submission',
				],
				'example'     => [
					'id' => 'frm001', 'name' => 'form', 'parent' => '0', 'children' => [],
					'settings' => [
						'fields' => [
							[ 'type' => 'text', 'label' => 'Name', 'required' => true, 'width' => '50' ],
							[ 'type' => 'email', 'label' => 'Email', 'required' => true, 'width' => '50' ],
							[ 'type' => 'textarea', 'label' => 'Message', 'required' => false, 'width' => '100' ],
						],
						'submitText' => 'Send Message',
						'actions'    => [ [ 'type' => 'email', 'subject' => 'New contact form submission' ] ],
					],
				],
			],
			// =================================================================
			// POST ELEMENTS (Single post/page)
			// =================================================================
			[
				'name'        => 'post-title',
				'category'    => 'post',
				'nestable'    => false,
				'description' => 'Dynamic post/page title. Use inside single post templates.',
				'keywords'    => [ 'post title', 'page title', 'dynamic', 'single' ],
				'key_settings'=> [
					'tag'        => 'h1 | h2 | h3',
					'link'       => 'true | false — wrap in permalink',
					'textAlign'  => 'left | center | right',
					'typography' => 'Font settings',
				],
				'example'     => [
					'id' => 'ptl001', 'name' => 'post-title', 'parent' => '0', 'children' => [],
					'settings' => [ 'tag' => 'h1', 'link' => false ],
				],
			],
			[
				'name'        => 'post-content',
				'category'    => 'post',
				'nestable'    => false,
				'description' => 'Dynamic post content body. Renders the_content() of the current post.',
				'keywords'    => [ 'post content', 'body', 'dynamic', 'single' ],
				'key_settings'=> [],
				'example'     => [
					'id' => 'pc001', 'name' => 'post-content', 'parent' => '0', 'children' => [],
					'settings' => [],
				],
			],
			[
				'name'        => 'post-excerpt',
				'category'    => 'post',
				'nestable'    => false,
				'description' => 'Dynamic post excerpt.',
				'keywords'    => [ 'excerpt', 'summary', 'post', 'dynamic' ],
				'key_settings'=> [
					'wordLimit'   => 'Number of words to show',
					'moreText'    => 'Read more link text',
					'moreLink'    => 'true | false',
				],
				'example'     => [
					'id' => 'pe001', 'name' => 'post-excerpt', 'parent' => '0', 'children' => [],
					'settings' => [ 'wordLimit' => 30, 'moreText' => 'Read more', 'moreLink' => true ],
				],
			],
			[
				'name'        => 'post-meta',
				'category'    => 'post',
				'nestable'    => false,
				'description' => 'Post metadata — date, author, category, comments count. Configurable fields.',
				'keywords'    => [ 'meta', 'date', 'author', 'category', 'post info' ],
				'key_settings'=> [
					'showDate'     => 'true | false',
					'showAuthor'   => 'true | false',
					'showCategory' => 'true | false',
					'showComments' => 'true | false',
					'separator'    => 'Separator string between items',
				],
				'example'     => [
					'id' => 'pm001', 'name' => 'post-meta', 'parent' => '0', 'children' => [],
					'settings' => [ 'showDate' => true, 'showAuthor' => true, 'showCategory' => true ],
				],
			],
			[
				'name'        => 'post-taxonomy',
				'category'    => 'post',
				'nestable'    => false,
				'description' => 'Display post taxonomy terms (categories, tags, custom taxonomies) as clickable links.',
				'keywords'    => [ 'taxonomy', 'category', 'tag', 'terms', 'post' ],
				'key_settings'=> [
					'taxonomy'  => 'Taxonomy slug (e.g. "category", "post_tag", "product_cat")',
					'separator' => 'Separator between terms',
				],
				'example'     => [
					'id' => 'ptax001', 'name' => 'post-taxonomy', 'parent' => '0', 'children' => [],
					'settings' => [ 'taxonomy' => 'category', 'separator' => ', ' ],
				],
			],
			[
				'name'        => 'post-author',
				'category'    => 'post',
				'nestable'    => false,
				'description' => 'Author information block — avatar, name, bio.',
				'keywords'    => [ 'author', 'bio', 'profile', 'post', 'writer' ],
				'key_settings'=> [
					'showAvatar'  => 'true | false',
					'showName'    => 'true | false',
					'showBio'     => 'true | false',
					'avatarSize'  => 'Avatar size in px',
				],
				'example'     => [
					'id' => 'pa001', 'name' => 'post-author', 'parent' => '0', 'children' => [],
					'settings' => [ 'showAvatar' => true, 'showName' => true, 'showBio' => true ],
				],
			],
			[
				'name'        => 'post-navigation',
				'category'    => 'post',
				'nestable'    => false,
				'description' => 'Previous/next post navigation links.',
				'keywords'    => [ 'navigation', 'previous', 'next', 'post', 'pagination' ],
				'key_settings'=> [
					'prevText' => 'Previous link label',
					'nextText' => 'Next link label',
					'showImage'=> 'true | false — show featured image',
				],
				'example'     => [
					'id' => 'pn001', 'name' => 'post-navigation', 'parent' => '0', 'children' => [],
					'settings' => [ 'prevText' => 'Previous', 'nextText' => 'Next' ],
				],
			],
			[
				'name'        => 'post-comments',
				'category'    => 'post',
				'nestable'    => false,
				'description' => 'WordPress comments section with comment list and form.',
				'keywords'    => [ 'comments', 'discussion', 'replies', 'post' ],
				'key_settings'=> [],
				'example'     => [
					'id' => 'pcom001', 'name' => 'post-comments', 'parent' => '0', 'children' => [],
					'settings' => [],
				],
			],
			[
				'name'        => 'related-posts',
				'category'    => 'post',
				'nestable'    => false,
				'description' => 'Related posts grid based on shared categories/tags.',
				'keywords'    => [ 'related', 'posts', 'suggested', 'more articles' ],
				'key_settings'=> [
					'count'     => 'Number of related posts',
					'relation'  => 'category | tag',
					'columns'   => 'Grid columns',
					'showImage' => 'true | false',
				],
				'example'     => [
					'id' => 'rp001', 'name' => 'related-posts', 'parent' => '0', 'children' => [],
					'settings' => [ 'count' => 3, 'columns' => '3', 'showImage' => true ],
				],
			],
			// =================================================================
			// QUERY / LOOP
			// =================================================================
			[
				'name'        => 'posts',
				'category'    => 'query',
				'nestable'    => true,
				'description' => 'Query loop element — fetches posts/CPT entries and renders its children as a template for each result. Essential for blog listings, portfolios, product grids.',
				'keywords'    => [ 'posts', 'loop', 'query', 'blog', 'grid', 'listing' ],
				'key_settings'=> [
					'query'   => [
						'postType'  => 'post | page | product | custom-cpt',
						'perPage'   => 'Number per page',
						'orderBy'   => 'date | title | menu_order | rand',
						'order'     => 'ASC | DESC',
						'taxQuery'  => 'Taxonomy filter',
						'metaQuery' => 'Custom meta filter',
					],
					'columns' => 'Grid columns (CSS: 1 | 2 | 3 | 4)',
					'layout'  => 'grid | masonry | list',
					'gap'     => 'Gap between items',
				],
				'example'     => [
					'id' => 'posts001', 'name' => 'posts', 'parent' => '0', 'children' => [ 'postcard001' ],
					'settings' => [
						'query'   => [ 'postType' => 'post', 'perPage' => 9, 'orderBy' => 'date', 'order' => 'DESC' ],
						'columns' => '3',
						'gap'     => [ 'row' => '30px', 'column' => '30px' ],
					],
				],
			],
			[
				'name'        => 'pagination',
				'category'    => 'query',
				'nestable'    => false,
				'description' => 'Pagination links for a query loop. Place after a "posts" element.',
				'keywords'    => [ 'pagination', 'pages', 'next', 'prev', 'numbered' ],
				'key_settings'=> [
					'prevText' => 'Previous label',
					'nextText' => 'Next label',
					'type'     => 'numbered | load-more | infinite-scroll',
				],
				'example'     => [
					'id' => 'pg001', 'name' => 'pagination', 'parent' => '0', 'children' => [],
					'settings' => [ 'type' => 'numbered' ],
				],
			],
			[
				'name'        => 'template',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Embed an existing Bricks template (reusable section) into the current page.',
				'keywords'    => [ 'template', 'include', 'embed', 'reuse', 'component' ],
				'key_settings'=> [
					'template' => 'Template post ID to embed',
				],
				'example'     => [
					'id' => 'tpl001', 'name' => 'template', 'parent' => '0', 'children' => [],
					'settings' => [ 'template' => 123 ],
				],
			],
			[
				'name'        => 'sidebar',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'WordPress registered sidebar widget area.',
				'keywords'    => [ 'sidebar', 'widgets', 'widget area' ],
				'key_settings'=> [
					'sidebar' => 'Registered sidebar ID',
				],
				'example'     => [
					'id' => 'sb001', 'name' => 'sidebar', 'parent' => '0', 'children' => [],
					'settings' => [ 'sidebar' => 'sidebar-1' ],
				],
			],
			[
				'name'        => 'map',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Google Maps embed with address or coordinates.',
				'keywords'    => [ 'map', 'google maps', 'location', 'address', 'embed' ],
				'key_settings'=> [
					'address' => 'Full address string',
					'zoom'    => 'Map zoom level (1-20)',
					'height'  => 'Map height (CSS value)',
					'mapType' => 'roadmap | satellite | hybrid | terrain',
				],
				'example'     => [
					'id' => 'map001', 'name' => 'map', 'parent' => '0', 'children' => [],
					'settings' => [ 'address' => '1600 Amphitheatre Pkwy, Mountain View, CA', 'zoom' => 14, 'height' => [ 'size' => 400, 'unit' => 'px' ] ],
				],
			],
			[
				'name'        => 'post-toc',
				'category'    => 'post',
				'nestable'    => false,
				'description' => 'Automatic table of contents generated from heading elements in post content.',
				'keywords'    => [ 'table of contents', 'toc', 'headings', 'navigation', 'anchor' ],
				'key_settings'=> [
					'minHeadings' => 'Min heading depth (h2 | h3)',
					'maxHeadings' => 'Max heading depth',
					'title'       => 'TOC block title',
				],
				'example'     => [
					'id' => 'toc001', 'name' => 'post-toc', 'parent' => '0', 'children' => [],
					'settings' => [ 'title' => 'Table of Contents', 'minHeadings' => 'h2', 'maxHeadings' => 'h3' ],
				],
			],
			[
				'name'        => 'rating',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Star rating display element.',
				'keywords'    => [ 'rating', 'stars', 'review', 'score' ],
				'key_settings'=> [
					'rating' => 'Rating value (0-5)',
					'max'    => 'Max rating (default 5)',
				],
				'example'     => [
					'id' => 'rat001', 'name' => 'rating', 'parent' => '0', 'children' => [],
					'settings' => [ 'rating' => 4.5, 'max' => 5 ],
				],
			],
			[
				'name'        => 'animated-typing',
				'category'    => 'general',
				'nestable'    => false,
				'description' => 'Animated typewriter text effect that cycles through multiple strings.',
				'keywords'    => [ 'typing', 'typewriter', 'animation', 'text', 'dynamic' ],
				'key_settings'=> [
					'strings' => '["First string", "Second string", "Third string"]',
					'speed'   => 'Typing speed in ms per character',
				],
				'example'     => [
					'id' => 'at001', 'name' => 'animated-typing', 'parent' => '0', 'children' => [],
					'settings' => [ 'strings' => [ 'Build faster', 'Design better', 'Launch sooner' ], 'speed' => 80 ],
				],
			],
		];
	}
}

# Bricks Builder Hooks & Filters (verified 2.3.7)

All hooks use the `bricks/` namespace. Source: `includes/*.php` across the parent theme.

## Element hooks

```php
// Register/remove elements from panel
apply_filters( 'bricks/builder/elements', $element_names )

// Element control groups & controls (per element)
apply_filters( 'bricks/elements/{element_name}/control_groups', $control_groups )
apply_filters( 'bricks/elements/{element_name}/controls', $controls )
apply_filters( 'bricks/elements/{element_name}/scripts', $scripts )

// Element render output
apply_filters( 'bricks/element/render', $html, $element )

// Element settings array before render
apply_filters( 'bricks/element/settings', $settings, $element )

// Root HTML attributes
apply_filters( 'bricks/element/set_root_attributes', $attributes, $element )

// All element attributes (including custom attribute groups)
apply_filters( 'bricks/element/render_attributes', $attributes, $key, $element )
```

## Frontend rendering hooks

```php
// Full element HTML output
apply_filters( 'bricks/frontend/render_element', $html, $element )

// Content render (dynamic data processing)
apply_filters( 'bricks/frontend/render_data', $content, $post_id )
do_action( 'bricks/frontend/before_render_data', $post_id )
do_action( 'bricks/frontend/after_render_data', $post_id )

// Content wrapper tag and attributes
apply_filters( 'bricks/content/tag', $tag )
apply_filters( 'bricks/content/attributes', $attributes )
apply_filters( 'bricks/header/attributes', $attributes )
apply_filters( 'bricks/footer/attributes', $attributes )

// Content injection points
do_action( 'bricks/content/html_after_begin' )
do_action( 'bricks/content/html_before_end' )

// Header/footer rendering
do_action( 'bricks/render_header' )
do_action( 'bricks/render_footer' )

// SEO and meta
apply_filters( 'bricks/frontend/disable_seo', $disable )
apply_filters( 'bricks/frontend/disable_opengraph', $disable )
```

## Template & conditions hooks

```php
// Active templates (which header/content/footer templates apply)
apply_filters( 'bricks/active_templates', $templates, $post_id, $content_type )

// Template condition scoring
apply_filters( 'bricks/screen_conditions/scores', $scores, $conditions, $post_id )

// Content type detection
apply_filters( 'bricks/database/content_type', $content_type )

// Supported content types for preview
apply_filters( 'bricks/template_preview/supported_content_types', $types )
```

## Query hooks

```php
// Before/after loop execution
do_action( 'bricks/query/before_loop', $query )
do_action( 'bricks/query/after_loop', $query )

// Force re-run query (skip cache)
apply_filters( 'bricks/query/force_run', $force, $query )

// Loop render output
apply_filters( 'bricks/frontend/render_loop', $html, $query )

// Query loop trail (pagination/infinite scroll)
apply_filters( 'bricks/render_query_loop_trail', $html, $query )

// Related posts query vars
apply_filters( 'bricks/related_posts/query_vars', $query_vars )
```

## Dynamic data hooks

```php
// Register custom data providers
do_action( 'bricks/dynamic_data/register_providers' )
do_action( 'bricks/dynamic_data/tags_registered' )

// Exclude specific tags
apply_filters( 'bricks/dynamic_data/exclude_tags', $excluded_tags )

// Replace nonexistent tags (remove or leave)
apply_filters( 'bricks/dynamic_data/replace_nonexistent_tags', false )

// Tag value after parsing
apply_filters( 'bricks/dynamic_data/tag_value_parsed', $value, $tag, $post_id, $context )

// Before/after action rendering
do_action( 'bricks/dynamic_data/before_do_action', $tag )
do_action( 'bricks/dynamic_data/after_do_action', $tag )
```

## Theme styles hooks

```php
// Modify stored theme styles
apply_filters( 'bricks/theme_styles', $styles )

// Theme style control groups & controls
apply_filters( 'bricks/theme_styles/control_groups', $groups )
apply_filters( 'bricks/theme_styles/controls', $controls )

// Theme style name
apply_filters( 'bricks/theme_style_name', $name, $style_id )
```

## CSS generation hooks

```php
// After CSS file generated
do_action( 'bricks/generate_css_file', $file_path, $type )

// Web font loading
apply_filters( 'bricks/assets/load_webfonts', $webfonts )
```

## Builder hooks

```php
// Builder i18n strings (used for custom category labels)
apply_filters( 'bricks/builder/i18n', $i18n )

// Override post ID context in builder
apply_filters( 'bricks/builder/data_post_id', $post_id )

// Page type detection
apply_filters( 'bricks/builder/current_page_type', $page_type )

// Save notification messages
apply_filters( 'bricks/builder/save_messages', $messages )

// First element category in panel
apply_filters( 'bricks/builder/first_element_category', $category )

// Standard/system fonts list
apply_filters( 'bricks/builder/standard_fonts', $fonts )

// Placeholder image URL
apply_filters( 'bricks/placeholder_image', $url )

// Template types in control options
apply_filters( 'bricks/setup/control_options', $options )
```

## Form hooks

```php
// Custom form action handlers
do_action( 'bricks/form/action/{action_type}', $form )
do_action( 'bricks/form/custom_action', $form )
```

## Breadcrumbs hooks

```php
apply_filters( 'bricks/breadcrumbs/home_label', $label )
apply_filters( 'bricks/breadcrumbs/separator', $separator )
apply_filters( 'bricks/breadcrumbs/items', $items )
```

## Authentication hooks

```php
apply_filters( 'bricks/auth/custom_redirect_url', $url )
apply_filters( 'bricks/auth/custom_login_redirect', $url )
apply_filters( 'bricks/auth/custom_lost_password_redirect', $url )
apply_filters( 'bricks/auth/custom_registration_redirect', $url )
apply_filters( 'bricks/auth/custom_reset_password_redirect', $url )
```

## Conditions hooks

```php
// Custom condition groups
apply_filters( 'bricks/conditions/groups', $groups )

// Custom condition options
apply_filters( 'bricks/conditions/options', $options )

// Condition evaluation result
apply_filters( 'bricks/conditions/result', $result, $condition, $element )
```

## Security & code hooks

```php
// Allowed HTML tags (extends wp_kses)
apply_filters( 'bricks/allowed_html_tags', $tags )

// Block/allow code execution
apply_filters( 'bricks/code/disallow_keywords', $keywords )
apply_filters( 'bricks/code/disable_execution', $disable )
apply_filters( 'bricks/code/allow_execution', $allow )
```

## AJAX actions (wp_ajax_bricks_*)

| Action | Purpose |
|---|---|
| `bricks_save_post` | Save element trees + page settings |
| `bricks_publish_post` | Publish post from builder |
| `bricks_create_autosave` | Create autosave revision |
| `bricks_save_color_palette` | Save color palette option |
| `bricks_save_theme_style` | Save theme style option |
| `bricks_update_breakpoints` | Update breakpoints option |
| `bricks_regenerate_css_file` | Regenerate CSS files |
| `bricks_render_element` | Preview/re-render single element |

All AJAX actions verify nonce `bricks-nonce-builder` + `Capabilities::current_user_can_use_builder()`.

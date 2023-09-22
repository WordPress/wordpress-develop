# Twenty Twenty-Four

Welcome to the development repository for the default theme that will launch with [WordPress 6.4](https://make.wordpress.org/core/6-4/).

<img width="1920" alt="2023" src="https://github.com/WordPress/twentytwentyfour/assets/1813435/d965b75d-03cd-4365-b963-b3862d482329">

Twenty Twenty-Four is built as a [block theme](https://developer.wordpress.org/block-editor/how-to-guides/themes/block-theme-overview/). The theme aims to ship with as little CSS as possible: our goal is for all theme styles to be configured through [`theme.json`](https://developer.wordpress.org/block-editor/how-to-guides/themes/theme-json/) and editable through Global Styles. The theme development team will work closely with [Gutenberg](https://github.com/wordpress/gutenberg) contributors to build design tools in the block editor that enable this goal.

You can view a demo of this theme at [2024.wordpress.net](https://2024.wordpress.net/), which is synced to `trunk` branch of this repository every 2 minutes.

## Contributing

If you would like to contribute code, the list of [open issues](https://github.com/WordPress/twentytwentyfour/issues) is a great place to start looking for tasks — but contributing is not just for developers. There are many opportunities to help with testing, triage, discussion, design, building [patterns](https://github.com/WordPress/twentytwentyfour/issues?q=is%3Aissue+is%3Aopen+label%3A%22%5BComponent%5D+Block+Patterns%22) and templates, and more. 

If you'd like to help with triage, let @luminuu and @MaggieCabrera know in [WordPress.org's Slack instance](https://make.wordpress.org/chat/). We'll help you get set up with the ability to add labels to issues and PRs.

## Getting Started

### Development

1. Set up a WordPress instance, we recommend [wp-env](https://developer.wordpress.org/block-editor/handbook/tutorials/devenv/) or [Local](https://localwp.com/) as an alternative to docker. Alternatively you can use [WordPress Playground](https://developer.wordpress.org/playground/) to test the theme directly in the browser.
2. Clone / download this repository into your `/wp-content/themes/` directory.
3. Install and activate the [Gutenberg plugin](https://wordpress.org/plugins/gutenberg/).

Also, consider enabling [development mode](https://make.wordpress.org/core/2023/07/14/configuring-development-mode-in-6-3/) with `define( 'WP_DEVELOPMENT_MODE', 'theme' );` in your `wp-config.php`. This will help minimize caching of `theme.json` while you're developing.

### Design

The theme is designed in [Figma](https://www.figma.com/file/AlYr03vh4dVimwYwQkTdf6/Twenty-Twenty-Four?type=design&t=C79166eDp3vX7OOD-6). You can contribute by designing one of the [patterns](https://github.com/WordPress/twentytwentyfour/issues?q=is%3Aissue+is%3Aopen+label%3A%22%5BComponent%5D+Block+Patterns%22) planned for Twenty Twenty-Four. 

As a default WordPress theme, it is important to leverage the existing design system wherever possible. That is the font sizes, [color palette choices](https://github.com/WordPress/twentytwentyfour/pull/106), and [spacing presets](https://github.com/WordPress/twentytwentyfour/pull/102). All patterns and templates will leverage this systems extensively. 

## Token Cheat-sheet

### Colors

| Figma Token | CSS Variable | `color` | `name` | `slug` | decorative visual of color |
|------------|-----------------------------|-----------|------|--------|----------------------------------------------------------|
| `Base/One` | `--wp--preset--color--base` | `#f9f9f9` | Base | `base` | ![](https://placehold.co/15x15/f9f9f9/f9f9f9.png) |
| `Base/Two` | `--wp--preset--color--base-2` | `#ffffff` | Base / Two | `base-2` | ![](https://placehold.co/15x15/ffffff/ffffff.png) |
| `Base/Three` | `--wp--preset--color--base-3` | `#00000025` | Base / Three | `base-3` | ![](https://placehold.co/15x15/00000025/00000025.png) |
| `Contrast/One` | `--wp--preset--color--contrast` | `#111111` | Contrast | `contrast` | ![](https://placehold.co/15x15/111111/111111.png) |
| `Contrast/Two` | `--wp--preset--color--contrast-2` | `#636363` | Contrast / Two | `contrast-2` | ![](https://placehold.co/15x15/636363/636363.png) |
| `Contrast/Three` | `--wp--preset--color--contrast-3` | `#a4a4a4` | Contrast / Three | `contrast-3` | ![](https://placehold.co/15x15/a4a4a4/a4a4a4.png) |
| `Accent/One` | `--wp--preset--color--accent` | `#cfcabe` | Accent | `accent` | ![](https://placehold.co/15x15/cfcabe/cfcabe.png) |
| `Accent/Two` | `--wp--preset--color--accent-2` | `#c2a990` | Accent / Two | `accent-2` | ![](https://placehold.co/15x15/c2a990/c2a990.png) |
| `Accent/Three` | `--wp--preset--color--accent-3` | `#d8613c` | Accent / Three | `accent-3` | ![](https://placehold.co/15x15/d8613c/d8613c.png) |
| `Accent/Four` | `--wp--preset--color--accent-4` | `#b1c5a4` | Accent / Four | `accent-4` | ![](https://placehold.co/15x15/b1c5a4/b1c5a4.png) |
| `Accent/Five` | `--wp--preset--color--accent-5` | `#b5bdbc` | Accent / Five | `accent-5` | ![](https://placehold.co/15x15/b5bdbc/b5bdbc.png) |

### Layout presets

| `theme.json` preset | `theme.json` value |
|---------------|---------|
| `contentSize` | `620px` |
| `wideSize`    | `1280px` |

### Spacing presets

| Figma Token | CSS Variable | `theme.json` value | `theme.json` slug |
|-------------|--------------|--------------------|-------------------|
| `Spacing/10` | `--wp--preset--spacing--10` | `min(1rem, 2vw)` | `10` |
| `Spacing/20` | `--wp--preset--spacing--20` | `min(1.5rem, 3vw)` | `20` |
| `Spacing/30` | `--wp--preset--spacing--30` | `min(2.5rem, 5vw)` | `30` |
| `Spacing/40` | `--wp--preset--spacing--40` | `min(4rem, 8vw)` | `40` |
| `Spacing/50` | `--wp--preset--spacing--50` | `min(6.5rem, 13vw)` | `50` |
| `Spacing/60` | `--wp--preset--spacing--60` | `min(10.5rem, 24vw)` | `60` |

### Pattern creation guidelines

[Reference guide for patterns in the handbook](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-patterns/). 
A few things to have in mind when building patterns for the default theme:

- **Category selection** 

When creating WordPress block patterns, it's important to carefully choose the appropriate category for your pattern. WordPress provides a set of default categories, each serving a specific purpose. Let's stick to using the default categories. We can add multiple of them separating them by commas. The list of the slug is [here](https://github.com/WordPress/gutenberg/blob/c20350c1d246163201375f090b0b7b4ab49b1dad/packages/block-editor/src/components/inserter/block-patterns-tab.js#L35).

- **Hiding patterns from the inserter**

You can control the visibility of your block pattern in the inserter by adding the following line of code when registering the pattern:

We do this for patterns we don't want the user to access via the inserter or the pattern library. This is usually the case for utility patterns that we create for translation purposes such as the 404 pattern.

We do this by adding the following line:

` * Inserter: no`

Let's prefix hidden patterns using `hidden-` when we name the pattern file.

- **Different translation functions and when to use them**

WordPress block patterns should be [internationalized](https://developer.wordpress.org/apis/internationalization/internationalization-guidelines/) to make them accessible to a global audience.

`esc_html_x()`: Employ this function when you need to translate and escape text for display within HTML. It's useful for multilingual websites as it provides translation support while also ensuring HTML safety.

`esc_html__()`: Similar to `esc_html_x()`, use this function for translating and escaping HTML-embedded text. It's a simpler version when context-specific translations are not needed.

`esc_attr__()` and `esc_attr_x()`: Use this function to escape and sanitize text meant for HTML attributes, such as image source URLs or link targets. It helps prevent security vulnerabilities by ensuring that user inputs are safe for use in attributes.

`esc_html_e`: works just like `esc_html__()` but you don't need to use `echo` to output the string

When we have simple HTML tags in our translatable strings we would use `echo wp_kses_post( __( 'Lorem ipsum <em>Hello</em> dolor sit amet.', 'texdomain' ) );`. This syntax is clearer for translators than using `sprintf()` and it allows them to remove the markup if it doesn't work on their own language.

These functions enhance security and support localization efforts in WordPress block patterns, ensuring that text is safe and can be easily translated.

- **Patterns with images**

To create dynamic image links in your block patterns, utilize the `get_template_directory_uri()` function. This function retrieves the URL of the current theme's directory, ensuring that the image links are relative to the theme and work correctly even if the website's directory structure changes or if we are using a child theme. This is essential for maintaining the stability and portability of your patterns.

Make sure to add alt text to your images and to make sure to remove the IDs from them. An example would be:

```
<!-- wp:image {"id":125,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://wp-stable.test/wp-content/themes/twentytwentyfour/assets/images/project.webp" alt="" class="wp-image-125"/></figure>
<!-- /wp:image -->
```

would turn into

```
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/project.webp" alt="<?php echo esc_attr_x( 'Picture of a building', 'Alt text for project picture', 'twentytwentyfour' ); ?>"/></figure>
<!-- /wp:image -->
```

- **Use of Post Types, Block Types and Template Types**

We use Block Types when the pattern uses custom markup for a specific block or one of the default template parts (footer and header). Using this will suggest the pattern when someone inserts said block or template part. This is commonly used for query, post-content block, template or footer.

Template Types is used when we want our pattern as a suggestion for a specific template. In this case we provide the template slug (404, home, single...)

Post Types is used to restrict the post type we want the pattern to be used for. commonly used for full page patterns.

- **Spacing, colors and font sizes**

Using presets for spacing, font sizes, and colors in WordPress block patterns is preferred over hardcoded values for three key reasons:

Consistency: Presets ensure a uniform design across the theme, promoting a cohesive visual identity.

Scalability: They make global design changes easier during development, saving time and effort.

Accessibility: Presets facilitate adherence to accessibility standards, making your patterns more usable and readable for a wider audience.

- **Other tips**

In the same way we remove IDs from image blocks, we need to remove queryId from query blocks too. Also, if any of our template parts have a theme attribute, that needs to remove too.

`<!-- wp:query {"queryId":18,"query":{"perPage":8,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true}} -->`

turns into 

`<!-- wp:query {"query":{"perPage":8,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true}} -->`

and 

`<!-- wp:template-part {"slug":"header-portfolio","theme":"twentytwentyfour","area":"header"} /-->`

turns into 

`<!-- wp:template-part {"slug":"header-portfolio","area":"header"} /-->`

If we are constantly assigning properties to the same block over and over again (ie: border radius to images), consider moving those properties to the theme.json. 

When building full page patterns, let's prefix them by using page-

One way to control the order in which patterns are displayed in the inserter is by changing the name of the file (they are sorted alphabetically)

### Tips for Contributors

- As stated above, a goal for the theme is to have as little CSS as possible. Much of the theme's visual treatments should be handled by the Block Editor and Global Styles. As a general rule, if multiple themes would benefit from the CSS you're considering adding, it might reasonably be provided by Gutenberg instead. Let's include clear code comments for any CSS we do include.
- Similarly, let's refrain from building any custom-built PHP or JavaScript-based workarounds for functionality that might reasonably be provided by the Block Editor, let's keep the code simple to help with future maintenance.
- In accordance to those last two bullets, this theme has no required build process.
- If you've helped contribute to the theme in any way, you deserve credit! Folks will be updating [CONTRIBUTORS.md](CONTRIBUTORS.md) periodically with names of contributors, but feel free to open a PR or issue if we leave someone out.

## Requirements

- Gutenberg plugin (latest)
- WordPress 6.4+
- PHP 7.0+
- License: [GPLv2](http://www.gnu.org/licenses/gpl-2.0.html) or later

Some theme features / PRs may require Gutenberg trunk and will be described or tagged accordingly.

### Testing

Optionally, to run tests locally, you will also need:

- [Node.js](https://nodejs.org/en/)
  - It's recommended that you install Node through [nvm](https://github.com/nvm-sh/nvm#intro), since it's the tool used by the CLI to select the node version being used.
- [Composer](https://getcomposer.org/)

You can install the test-specific development dependencies by running `npm i && composer install`. The following test commands are then available:

- `npm run lint:css` lints and autofixes where possible the CSS
- `composer run analyze [filename.php]` statically analyzes PHP for bugs
- `composer run lint` checks PHP for syntax errors
- `composer run standards:check` checks PHP for standards errors according to [WordPress coding standards](https://developer.wordpress.org/coding-standards/)
- `composer run standards:fix` attemps to automatically fix errors

## Resources

- [Twenty Twenty-Four Figma Mockups](https://www.figma.com/file/AlYr03vh4dVimwYwQkTdf6/Twenty-Twenty-Four?type=design&node-id=16%3A14852&mode=design&t=yad81XRtp200JLes-1)
- [Setting up a development environment](https://developer.wordpress.org/block-editor/handbook/tutorials/devenv/)
- [Block Theme documentation](https://developer.wordpress.org/block-editor/how-to-guides/themes/block-theme-overview)
- [Global Styles & theme.json documentation](https://developer.wordpress.org/block-editor/how-to-guides/themes/theme-json/)

## Timeline

The theme will be released with WordPress 6.4 and follow the key dates / milestones associated with [its development schedule](https://make.wordpress.org/core/6-4).

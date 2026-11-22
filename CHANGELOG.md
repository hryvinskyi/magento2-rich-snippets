# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-22

### Added
- JSON-LD output for storefront pages: one script block for each switched-on snippet that fits the page's kind, store
  view and conditions, written in the head or at the end of the body, compact or readable. A snippet that fails is
  logged and left out; the page is never broken.
- Snippets as JSON templates, evaluated by the `hryvinskyi/magento2-json-template` engine. Building blocks are snippets
  with no page of their own, included by code into others; an include loop is refused when saving.
- Placement by kind of page (home, product, category, CMS, every other page), by conditions built with the expression
  builder (product attributes, attribute set, category, product type, category page, CMS page, with ten operators), and
  by store view.
- The store's fields and lists for templates: product, product attributes, price and stock, reviews, image, variant,
  page, category, listed product, store and breadcrumb; the lists `product.gallery`, `reviews.latest`,
  `product.variants`, `category.products` and `breadcrumbs.items`. One data source serves the storefront, the preview and
  the catalog check, and reads a page's data in batches.
- Prices as the visitor sees them: the customer group and display currency that the full-page cache varies on, with tax
  as the **Prices in Snippets** setting says, for simple, virtual, downloadable, configurable, bundle and grouped
  products.
- Variant addresses with one query parameter for each configurable attribute, and the preselection of that variant when a
  product page opens at such an address.
- Page cache tags for each kind of page and store view (`rich_snippets_kind_<kind>_<store view ID>`,
  `rich_snippets_store_<store view ID>`), cleaned after a snippet change or a settings save is committed. A **Rich
  Snippets** cache type keeps the selection index of each store view.
- Removal of the theme's own microdata items that the snippets of a product page describe again (the Product item with its offer and rating, and items of any other type a snippet on the page is written as), on product pages that write a Product or ProductGroup snippet; other items stay.
- Requirement rules as JSON files for nine kinds of Google result: merchant listing, review stars, product variants,
  breadcrumb, organization, return policy, site name, FAQ and shipping policy.
- A library of 13 templates (Organization, Website name, Breadcrumbs, Local business, Product, Product with variants,
  Product list, Category, FAQ, Article, Return policy, Shipping policy, Postal address), with questions answered from the
  catalog and a recommended set of five.
- Admin pages under **Marketing** > **SEO & Search** > **Rich Snippets**: Overview, Snippets (list, switch, duplicate,
  delete, export, import with a preview), Library, the editor (Properties as a builder or as code, Pages, Preview and
  Checks) and Test.
- The page test: one storefront page read over HTTP as a visitor gets it, with its blocks, the verdict for each result
  type, the theme's markup and the reasons snippets are not output.
- The catalog check: every snippet built for every page in every store view without visiting the storefront, run nightly
  at a set time, after a product import or on request, with findings, a CSV download of the pages behind a finding and an
  optional e-mail.
- Settings in **Stores** > **Settings** > **Configuration** > **Hryvinskyi Extensions** > **Rich Snippets**: enabled,
  output position, format, prices in snippets, theme markup removal, the nightly check and a developer comment for each
  block.
- Permissions: **See the module**, **Change snippets**, **Run tests** and **Change the settings**.
- Extension points as pools in `di.xml`: field groups, lists, condition fields and operators, kinds of page, requirement
  rules, library templates and question suggesters, output positions, Overview items, snippet flags, price resolvers,
  attribute value resolvers, property suggestions and enums.
- Cron jobs in their own group: a minute job that runs a requested check, a scheduled job that requests the nightly check
  and a daily job that prunes old check data.
- The admin wording in `en_US` and 25 more locales, machine-translated. A count is worded in every plural form the
  admin's language has (Ukrainian one, few and many; Slovenian two; Irish and Maltese five forms …), through the admin
  UI kit's plural phrases, and each sentence the pages build is one translatable sentence per shape.

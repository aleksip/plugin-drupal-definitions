# Drupal Definitions Plugin for Pattern Lab

Use Drupal `.layouts.yml` definition files as pattern data files.


## Installing

To install and use the plugin run the following command in the Pattern Lab root directory:

```bash
composer require aleksip/plugin-drupal-definitions
```

This plugin works with pattern-specific `.layouts.yml` files. To be able to use the same files in Drupal, you can install the [Multiple Definition Files](https://github.com/aleksip/multiple_definition_files) module.


## Usage

Example `example_card.layouts.yml`:

```yml
# This key is not used by the plugin for anything.
#
# The plugin supports multiple layout definitions in one file if that is 
# required for some reason.
example_card:

  # This is the regular layout definition part.

  label: 'Example card'
  category: 'Cards'

  # Please note that Drupal requires layout templates to use the .html.twig
  # suffix, which is left out in layout definitions.
  #
  # The plugin expects the template file to be in the same directory as the
  # layout definition file. The path part is only used by Drupal.
  #
  # Pattern Lab shorthand syntax uses the template name without the .twig suffix 
  # so this pattern would be molecules-example-card-html.
  template: dist/_patterns/01-molecules/cards/example-card/example-card

  regions:
    card_type:
      label: Card type
    image:
      label: Image
    date:
      label: Date
    title:
      label: Title
    text:
      label: Text

  # This additional key is used by the plugin. Nothing under it is used by
  # Drupal core.
  example_values:
  
    # The 'base' key is reserved for base pattern data.
    base:

      # The 'meta' section is for pattern documentation metadata. This 
      # is equivalent to the YAML front matter section of a Pattern Lab .md 
      # documentation file. These values are merged with the contents of a
      # possible documentation file, so .md files can still be used for markdown
      # content.
      meta:

        title: 'Example card'
        state: inprogress

      # The 'data' section is for pattern data.
      data:

        card_type: 'Lorem ipsum'
      
        # It is possible to use Data Transform Plugin features too!
        image: atoms-img-3x2
      
        date: '19.11.2018'
        title: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit'
        text: "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
          eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad
          minim veniam, quis nostrud exercitation ullamco laboris nisi ut
          aliquip ex ea commodo consequat."

    # All other keys under 'example_values' are used to create pseudo-patterns.
    # Pseudo-pattern data is merged with base pattern data just like with 
    # regular pseudo-pattern data files.

    # The key is used as the name of the pseudo-pattern. So in Pattern Lab 
    # shorthand syntax this pseudo-pattern would be 
    # molecules-example-card-html-news-item. 
    news-item:

      # You can leave either 'meta' or 'data' out if not needed.
    
      data:
        card_type: 'News item'

    # It is also possible to hide the pseudo-patterns just like with regular
    # pseudo-pattern data files, by adding an underscore to the beginning of the 
    # pseudo-pattern key name.
    _event-1:

      data:

        # Yes, it is possible to use all Data Transform Plugin features!
        attributes:
          Attribute():
            class:
              - example-card
              - example-card--event
```

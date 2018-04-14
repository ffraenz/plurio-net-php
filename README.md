
# plurio-net-php

[![MIT license](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

This PHP library tries to simplify access to the XML interface of [Plurio.net](http://plurio.net), the database of cultural events in Luxembourg and the greater region.

## Features

- Fetches events from the Plurio.net XML interface
- Verifies data with XML schemas
- Calculates event occurrences and schedules from date ranges, date exclusions, timings and timing exceptions
- Replaces dates and times with `DateTime` objects configured with the correct timezone
- Injects category and function data from different sources
- Provides a simple interface and consistent data

## Example

Use [composer](https://getcomposer.org/) to install this library as a dependency.

```
$ composer require ffraenz/plurio-net-php
```

To create an `Export` instance, you need to provide an export url where the XML data gets fetched from. To get access to the XML interface, contact [Plurio.net](http://plurio.net).

```php
$exportUrl = 'PLURIO_NET_EXPORT_URL_HERE';
$export = new FFraenz\PlurioNet\Export($exportUrl);
$events = $export->getEvents();
```

The resulting PHP array `$events` is structured similarly to the following JSON-formatted data. Note, that all dates are returned as `DateTime` objects. The data below is shortened and ids are leetified.

```json
{
  "id": 1337,
  "name": "Spaghettisfest 2018",
  "subtitles": [
    "Since 1985 in Eppeldorf"
  ],
  "description": {
    "lu": "S\u00e4it iwwer 30 Joer versammelen sech...",
    "en": "For over 30 years hungry people gather..."
  },
  "location": {
    "id": 1337,
    "name": "Pompjeesbau Eppelduerf",
    "street": "Faubourg 14",
    "postcode": "L-9365",
    "locality": "Eppeldorf",
    "localityId": "L1337"
  },
  "contacts": [
    {
      "type": "url",
      "value": "http:\/\/spaghettisfest.lu"
    },
    {
      "type": "emailAddress",
      "value": "contact@eppelduerferjugend.lu",
      "function": {
        "id": "1337",
        "description": { "de": "Kontakt", "fr": "Contact" }
      }
    },
    {
      "type": "phoneNumber",
      "subtype": "mobile",
      "value": "00352123456789",
      "function": {
        "id": "1337",
        "description": { "de": "Kontakt", "fr": "Contact" }
      }
    }
  ],
  "occurrences": [
    {
      "allDay": false,
      "time": "2018-08-15 12:00:00",
      "endTime": "2018-08-16 03:00:00",
      "schedule": [
        {
          "time": "2018-08-15 12:00:00",
          "endTime": "2018-08-15 14:00:00",
          "description": "Spaghetti & Bar"
        },
        {
          "time": "2018-08-15 18:00:00",
          "endTime": "2018-08-15 22:00:00",
          "description": "Spaghetti & Bar"
        },
        {
          "time": "2018-08-15 22:00:00",
          "endTime": "2018-08-16 03:00:00",
          "description": "Bar"
        }
      ]
    }
  ],
  "pricing": [
    {
      "value": 12,
      "description": "Spaghetti All-You-Can-Eat"
    },
    {
      "value": 9,
      "description": "Spaghetti Small"
    }
  ],
  "categories": [
    {
      "id": 1337,
      "name": { "de": "Gastronomie", "en": "Gastronomy", "fr": "Gastronomie" },
      "parentName": { "de": "Freizeit", "en": "Leisure", "fr": "Loisirs" }
    }
  ],
  "organisations": [
    {
      "id": "1337",
      "name": "Eppelduerfer Jugend",
      "function": {
        "id": "1337",
        "description": { "de": "Organisation", "fr": "Organisation" }
      }
    }
  ],
  "images": [
    {
      "id": 1337,
      "url": "http:\/\/example.com/image.png",
      "title": "Image title",
      "alt": "Image alt",
      "position": "default"
    }
  ]
}
```

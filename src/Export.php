<?php

namespace FFraenz\PlurioNet;

use DateTime;
use DateTimeZone;
use DOMNode;

class Export extends Document
{
    /**
     * Default schema url
     * @var string
     */
    const DEFAULT_SCHEMA_URL = __DIR__ . '/../assets/schema-export.xsd';

    /**
     * Default timezone identifier
     * @var string
     */
    const DEFAULT_TIMEZONE = 'Europe/Luxembourg';

    /**
     * Pattern for time verification
     * @var string
     */
    const TIME_PATTERN = '/^[0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/';

    /**
     * Category listing
     * @var CategoryListing
     */
    protected $categoryListing;

    /**
     * Type listing
     * @var TypeListing
     */
    protected $typeListing;

    /**
     * Timezone object
     * @var DateTimeZone
     */
    protected $timezone;

    /**
     * Array of events
     * @var array
     */
    protected $events;

    /**
     * Constructor
     * @param string $documentUrl
     * @param string $schemaUrl (optional)
     */
    public function __construct(
        $documentUrl,
        $schemaUrl = self::DEFAULT_SCHEMA_URL,
        $timezone = self::DEFAULT_TIMEZONE
    ) {
        parent::__construct($documentUrl, $schemaUrl);
        $this->timezone = new DateTimeZone($timezone);
    }

    /**
     * Returns category listing instance.
     * @return CategoryListing
     */
    public function getCategoryListing()
    {
        if ($this->categoryListing === null) {
            $this->categoryListing = new CategoryListing();
        }
        return $this->categoryListing;
    }

    /**
     * Sets the category listing instance.
     * @param CategoryListing $categoryListing
     * @return Export Fluent interface
     */
    public function setCategoryListing($categoryListing)
    {
        $this->categoryListing = $categoryListing;
        return $this;
    }

    /**
     * Returns type listing instance.
     * @return TypeListing
     */
    public function getTypeListing()
    {
        if ($this->typeListing === null) {
            $this->typeListing = new TypeListing();
        }
        return $this->typeListing;
    }

    /**
     * Sets the type listing instance.
     * @param TypeListing $typeListing
     * @return Export Fluent interface
     */
    public function setTypeListing($typeListing)
    {
        $this->typeListing = $typeListing;
        return $this;
    }

    /**
     * Fetches events in current export.
     * @return array
     */
    public function getEvents()
    {
        if ($this->events === null) {
            // query event nodes
            $eventNodes = $this->queryNodes(
                $this->getDocumentNode(), '/plurio/agenda/event');

            // read event nodes and filter out invalid ones
            $this->events = array_filter(array_map(
                [$this, 'readEvent'], $eventNodes));
        }
        return $this->events;
    }

    /**
     * Reads event from given event node.
     * @param DOMNode $eventNode
     * @return array
     */
    protected function readEvent($eventNode)
    {
        // read and verify event id
        $id = $this->queryValue(
            $eventNode, '@id');

        if (empty($id)) {
            return null;
        }

        $id = intval($id);

        // read name
        $name = $this->queryValue(
            $eventNode, 'name');

        if (empty($name)) {
            return null;
        }

        // read subtitles
        $subtitles = array_filter($this->queryValues(
            $eventNode, ['subtitleOne', 'subtitleTwo']));

        // compose event
        return [
            'id'            => $id,
            'name'          => $name,
            'subtitles'     => $subtitles,
            'description'   => $this->readEventDescription($eventNode),
            'location'      => $this->readEventLocation($eventNode),
            'contacts'      => $this->readEventContacts($eventNode),
            'occurrences'   => $this->readEventOccurrences($eventNode),
            'pricing'       => $this->readEventPricing($eventNode),
            'categories'    => $this->readEventCategories($eventNode),
            'organisations' => $this->readEventOrganisations($eventNode),
            'images'        => $this->readEventImages($eventNode),
        ];
    }

    /**
     * Reads event descriptions in all the available languages.
     * @param DOMNode $eventNode
     * @return array
     */
    protected function readEventDescription($eventNode)
    {
        $descriptionNodes = $this->queryNodes($eventNode, [
            'longDescriptions/longDescription',
            'shortDescriptions/shortDescription',
        ]);

        // for each language, use the first valid description that can be found
        $languageDescriptionMap = [];
        foreach ($descriptionNodes as $descriptionNode) {
            $language = $this->queryValue($descriptionNode, '@language');

            // for each language, use the first occuring description
            if (!isset($languageDescriptionMap[$language])) {
                $languageDescriptionMap[$language] =
                    trim($descriptionNode->nodeValue);
            }
        }

        return $languageDescriptionMap;
    }

    /**
     * Reads location of given event node.
     * @param DOMNode $eventNode
     * @return array
     */
    protected function readEventLocation($eventNode)
    {
        $locationNode = current($this->queryNodes(
            $eventNode, 'relationsAgenda/placeOfEvent'));

        $id = intval($this->queryValue($locationNode, '@id'));
        $name = $this->queryValue($locationNode, 'name');
        $street = $this->queryValue($locationNode, 'street');
        $postcode = $this->queryValue($locationNode, 'postcode');
        $locality = $this->queryValue($locationNode, 'city');
        $localityId = $this->queryValue($locationNode, 'city/@localisation_id');

        // normalize streets and localities often deposited all-caps
        $street = mb_convert_case($street, MB_CASE_TITLE, 'UTF-8');
        $street = str_replace(
            ['Rue De', 'Route De'],
            ['Rue de', 'Route de'],
            $street);

        $locality = mb_convert_case($locality, MB_CASE_TITLE, 'UTF-8');

        return [
            'id' => $id,
            'name' => $name,
            'street' => $street,
            'postcode' => $postcode,
            'locality' => $locality,
            'localityId' => $localityId,
        ];
    }

    /**
     * Reads websites, phone numbers and email addresses from given event node.
     * @param DOMNode $eventNode
     * @return array
     */
    protected function readEventContacts($eventNode)
    {
        $contacts = [];

        // read website urls
        $websiteUrls = $this->queryValues(
            $eventNode, 'contactEvent/websites/website');
        foreach ($websiteUrls as $websiteUrl) {
            $websiteUrl = $this->filterUrl($websiteUrl);
            if ($websiteUrl !== null) {
                array_push($contacts, [
                    'type' => 'url',
                    'value' => $websiteUrl,
                ]);
            }
        }

        // read email addresses
        $emailAddressesNodes = $this->queryNodes(
            $eventNode, 'contactEvent/emailAdresses/emailAdress');
        foreach ($emailAddressesNodes as $node) {
            $emailAddress = $this->queryValue($node, 'emailAdressUrl');
            $functionTypeId = $this->queryValue($node, 'emailAdressFunctionId');

            $function = !empty($functionTypeId)
                ? $this->getTypeListing()->getType($functionTypeId)
                : null;

            array_push($contacts, [
                'type' => 'emailAddress',
                'value' => $emailAddress,
                'function' => $function,
            ]);
        }

        // read phone numbers
        $phoneNumberNodes = $this->queryNodes(
            $eventNode, 'contactEvent/phoneNumbers/phoneNumber');
        foreach ($phoneNumberNodes as $node) {
            $phoneNumberType = $this->queryValue($node, '@phoneType');
            $phoneNumber = $this->queryValue($node, 'mainNumber');
            $areaCode = $this->queryValue($node, 'phoneNumberAreaCode');
            $functionTypeId = $this->queryValue($node, 'phoneNumberFunctionId');

            if (!empty($areaCode)) {
                $phoneNumber = $areaCode . ' ' . $phoneNumber;
            }

            $function = !empty($functionTypeId)
                ? $this->getTypeListing()->getType($functionTypeId)
                : null;

            array_push($contacts, [
                'type' => 'phoneNumber',
                'subtype' => $phoneNumberType,
                'value' => $phoneNumber,
                'function' => $function,
            ]);
        }

        return $contacts;
    }

    /**
     * Reads event occurrences from given event node.
     * @param DOMNode $eventNode
     * @return array
     */
    protected function readEventOccurrences($eventNode)
    {
        // read event date span
        $fromDateString = $this->queryValue(
            $eventNode, 'date/dateFrom');
        $fromDate = new DateTime($fromDateString, $this->timezone);

        $toDateString = $this->queryValue(
            $eventNode, 'date/dateTo');
        $toDate = $toDateString !== null
            ? new DateTime($toDateString, $this->timezone)
            : null;

        // read weekday and date exclusions
        $weekdayExclusions = $this->queryValues(
            $eventNode, 'date/dateExclusions/dateWeekday');
        $dateExclusions = $this->queryValues(
            $eventNode, 'date/dateExclusions/dateDay');

        // read default timings
        $defaultTimings = array_map(
            [$this, 'readTiming'],
            $this->queryNodes($eventNode, 'timings/timing'));

        // collect weekday and date specific timings
        $dateTimingsMap = [];
        $weekdayTimingsMap = [];

        $timingExceptionNodes = $this->queryNodes(
            $eventNode, 'timings/timingExceptions/timingException');

        foreach ($timingExceptionNodes as $node) {
            $timingNode = current($this->queryNodes(
                $node, 'timing'));
            $timing = $this->readTiming($timingNode);

            // read weekday specific timing
            $weekdayString = $this->queryValue($node, 'dateWeekday');
            if ($weekdayString !== null) {
                if (!isset($weekdayTimingsMap[$weekdayString])) {
                    $weekdayTimingsMap[$weekdayString] = [$timing];
                } else {
                    array_push($weekdayTimingsMap[$weekdayString], $timing);
                }
            }

            // read date specific timing
            $dateString = $this->queryValue($node, 'dateDay');
            if ($dateString !== null) {
                if (!isset($dateTimingsMap[$dateString])) {
                    $dateTimingsMap[$dateString] = [$timing];
                } else {
                    array_push($dateTimingsMap[$dateString], $timing);
                }
            }
        }

        // iterate through the event date span day by day to collect occurrences
        $occurrences = [];
        $currentDateTime = clone $fromDate;

        do {
            $dateString = $currentDateTime->format('Y-m-d');
            $weekdayString = strtolower($currentDateTime->format('D'));

            // skip excluded dates and weekdays
            if (
                array_search($dateString, $dateExclusions) === false &&
                array_search($weekdayString, $weekdayExclusions) === false
            ) {
                // prepare all day occurrence
                $allDay = true;
                $startDateTime = clone $currentDateTime;
                $endDateTime = null;
                $schedule = [];

                // check which timing applies for the current date in order:
                // date specific, weekday specific or default timings
                $timings = $defaultTimings;
                if (isset($dateTimingsMap[$dateString])) {
                    $timings = $dateTimingsMap[$dateString];
                } else if (isset($weekdayTimingsMap[$weekdayString])) {
                    $timings = $weekdayTimingsMap[$weekdayString];
                }

                // check if timings are available for this occurrence
                if (!empty($timings)) {
                    // this is no longer an all day occurrence
                    $allDay = false;

                    // compose schedule for current date
                    $schedule = array_map(function($timing) use ($dateString) {
                        $startDateTime = new DateTime(
                            $dateString . ' ' . $timing['from'],
                            $this->timezone);

                        // check if there is a known end time
                        $endDateTime = null;
                        if ($timing['to'] !== null) {
                            $endDateTime = new DateTime(
                                $dateString . ' ' . $timing['to'],
                                $this->timezone);

                            // when the end time is situated before the start
                            // time, the span probably extends to the next day
                            if ($startDateTime > $endDateTime) {
                                $endDateTime->modify('+1 day');
                            }
                        }

                        return [
                            'time' => $startDateTime,
                            'endTime' => $endDateTime,
                            'description' => $timing['description'],
                        ];
                    }, $timings);

                    // sort schedule
                    usort($schedule, function ($a, $b) {
                        if ($a == $b) {
                            return 0;
                        }
                        return $a < $b ? -1 : 1;
                    });
                }

                // add occurrence to the stack
                array_push($occurrences, [
                    'allDay' => $allDay,
                    'time' => $schedule[0]['time'],
                    'endTime' => $schedule[count($schedule) - 1]['endTime'],
                    'schedule' => $schedule,
                ]);
            }

            // iterate to next day
            $currentDateTime->modify('+1 day');

        } while ($toDate !== null && $currentDateTime <= $toDate);

        return $occurrences;
    }

    /**
     * Reads and verifies a timing span from given timing node.
     * @param DOMNode $timingNode
     * @return array|null
     */
    protected function readTiming($timingNode)
    {
        $fromTime = $this->queryValue($timingNode, 'timingFrom');
        $toTime = $this->queryValue($timingNode, 'timingTo');
        $description = $this->queryValue($timingNode, 'timingDescription');

        // verify required from time
        if (empty($fromTime) ||
            preg_match(self::TIME_PATTERN, $fromTime) !== 1) {
            throw new Exception(sprintf(
                "'%s' is not a valid time.", $fromTime));
        }

        // verify optional to time
        if ($toTime !== null &&
            preg_match(self::TIME_PATTERN, $toTime) !== 1) {
            throw new Exception(sprintf(
                "'%s' is not a valid time.", $toTime));
        }

        return [
            'from' => $fromTime,
            'to' => $toTime,
            'description' => $description,
        ];
    }

    /**
     * Reads pricing from given event node.
     * @param DOMNode $eventNode
     * @return array|false Pricing array or false, if free of charge.
     */
    protected function readEventPricing($eventNode)
    {
        $freeOfCharge = $this->queryValue($eventNode, 'prices/@freeOfCharge');
        if ($freeOfCharge === 'true') {
            return false;
        }

        $priceNodes = $this->queryNodes($eventNode, 'prices/price');

        $pricing = [];
        foreach ($priceNodes as $node) {
            $description = $this->queryValue($node, 'priceDescription');
            $value = floatval($this->queryValue($node, 'priceValue'));

            array_push($pricing, [
                'value' => $value,
                'description' => $description,
            ]);
        }

        return $pricing;
    }

    /**
     * Reads category ids from the given event node and matches them to
     * categories from the category listing.
     * @param DOMNode $eventNode
     * @return array
     */
    protected function readEventCategories($eventNode)
    {
        $categories = [];

        $categoryListing = $this->getCategoryListing();
        $categoryIds = $this->queryValues($eventNode,
            'relationsAgenda/agendaCategories/category');

        foreach ($categoryIds as $categoryId) {
            $category = $categoryListing->getCategory($categoryId);
            if ($category !== null) {
                array_push($categories, $category);
            }
        }

        return $categories;
    }

    /**
     * Reads organisations from given event node.
     * @param DOMNode $eventNode
     * @return array
     */
    protected function readEventOrganisations($eventNode)
    {
        $organisationNodes = $this->queryNodes(
            $eventNode, 'relationsAgenda/organisationsToEvent/organisation');

        $organisations = [];
        foreach ($organisationNodes as $node) {
            $name = $node->nodeValue;
            $id = $this->queryValue($node, '@id');

            $functionId = $this->queryValue($node, '@type');
            $function = !empty($functionId)
                ? $this->getTypeListing()->getType($functionId)
                : null;

            array_push($organisations, [
                'id' => $id,
                'name' => $name,
                'function' => $function,
            ]);
        }

        return $organisations;
    }

    /**
     * Reads images from given event node.
     * @param DOMNode $eventNode
     * @return array
     */
    protected function readEventImages($eventNode)
    {
        $imageNodes = $this->queryNodes(
            $eventNode, 'relationsAgenda/pictures/picture');

        $images = [];
        foreach ($imageNodes as $node) {
            $id = intval($this->queryValue($node, '@id'));
            $position = $this->queryValue($node, '@position');
            $url = $this->queryValue($node, 'url');
            $title = $this->queryValue($node, 'title');
            $alt = $this->queryValue($node, 'alt');

            if (!empty($url)) {
                array_push($images, [
                    'id' => $id,
                    'url' => $url,
                    'title' => $title,
                    'alt' => $alt,
                    'position' => $position,
                ]);
            }
        }

        return $images;
    }

    /**
     * Adds missing scheme to urls.
     * @param string $url
     * @return string
     */
    protected function filterUrl($url)
    {
        if (empty($url)) {
            return null;
        }

        $info = parse_url($url);

        // append scheme if missing
        if (!isset($info['scheme'])) {
            $url = 'http://' . $url;
        }

        return $url;
    }
}

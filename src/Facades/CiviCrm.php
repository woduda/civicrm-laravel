<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Woduda\CiviCRM\Api\ActivityApi;
use Woduda\CiviCRM\Api\AddressApi;
use Woduda\CiviCRM\Api\ContactApi;
use Woduda\CiviCRM\Api\ContributionApi;
use Woduda\CiviCRM\Api\EmailApi;
use Woduda\CiviCRM\Api\EventApi;
use Woduda\CiviCRM\Api\GenericApi;
use Woduda\CiviCRM\Api\GroupApi;
use Woduda\CiviCRM\Api\NoteApi;
use Woduda\CiviCRM\Api\ParticipantApi;
use Woduda\CiviCRM\Api\PhoneApi;
use Woduda\CiviCRM\Api\RelationshipApi;
use Woduda\CiviCRM\Api\RelationshipTypeApi;
use Woduda\CiviCRM\Api\TagApi;

/**
 * @method static ContactApi          contacts()
 * @method static ActivityApi         activities()
 * @method static TagApi              tags()
 * @method static GroupApi            groups()
 * @method static EmailApi            emails()
 * @method static PhoneApi            phones()
 * @method static AddressApi          addresses()
 * @method static RelationshipApi     relationships()
 * @method static RelationshipTypeApi relationshipTypes()
 * @method static NoteApi             notes()
 * @method static EventApi            events()
 * @method static ParticipantApi      participants()
 * @method static ContributionApi     contributions()
 * @method static GenericApi          entity(string $name)
 * @method static array<mixed>        raw(string $entity, string $action, array<string, mixed> $params = [])
 *
 * @see \Woduda\CiviCRM\CiviCrmClient
 */
final class CiviCrm extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'civicrm';
    }
}

<?php

namespace Wexample\SymfonySearch\Enum;

/**
 * Where a search is being made from, which is what decides what it may answer.
 *
 * The legacy called this the "search action" and listed it as free strings in
 * every entity service — `header`, `bill_secondary_user`,
 * `invoice_admin_entity_form_user`. Half of them were marked `TODO Not tested`,
 * which is what a list nobody can enumerate ends up looking like.
 *
 * An application declares its own contexts in its own backed enum; nothing here
 * has to know them, because what travels is the string behind the case.
 */
enum SearchContext: string
{
    /** Everything the user may see, which is what a query says nothing about. */
    case DEFAULT = 'default';

    /** The search field of a layout, answering across every kind of result. */
    case HEADER = 'header';

    /** A form field picking one record, answering with one kind of result. */
    case FORM_FIELD = 'form_field';
}

<?php

namespace Wexample\SymfonySearch\Form\Type;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Wexample\SymfonyForms\Form\Traits\FieldOptionsTrait;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonySearch\Class\SearchableEntity;
use Wexample\SymfonySearch\Entity\SearchResult;
use Wexample\SymfonySearch\Form\DataTransformer\EntitySearchInputListTransformer;
use Wexample\SymfonySearch\Form\DataTransformer\EntitySearchInputTransformer;
use Wexample\SymfonySearch\Service\EntityPicker;
use Wexample\SymfonySearch\Service\SearchableRegistry;

/**
 * A field whose value is a record, picked by searching for it.
 *
 * What the form posts is an identifier; what it hands back is the record — the
 * one the search could have offered here, checked again on the way in. What
 * the person sees is a search box narrowed to one kind of record, then the
 * picked one.
 *
 * `multiple` makes it several records rather than one: one identifier posted
 * per value, as any multi-valued field does, and one row per record shown.
 * `search` turned off leaves the rows without the box, for a relation filled
 * by something else — an uploader — where the field is only how a record is
 * taken back out.
 *
 * Which kind is read from the relation the field is bound to: `client` on an
 * invoice asks for clients without being told. `class` or `entity_type` say it
 * where there is no relation to read — an unmapped field.
 *
 * It lives here and not in `wexample/symfony-forms` because it offers this
 * package's feature and would be useless without it. The dependency only reads
 * one way: forms knows nothing of search, and search already had forms through
 * the api.
 */
class EntitySearchInputType extends AbstractType
{
    use FieldOptionsTrait;

    /** Which kind of record the field asks for, by its search type. */
    public const string OPTION_ENTITY_TYPE = 'entity_type';

    /** Which kind of record the field asks for, by its class. */
    public const string OPTION_CLASS = 'class';

    /** Where the search is made from, which decides what it may answer. */
    public const string OPTION_SEARCH_CONTEXT = 'search_context';

    /** Several records rather than one, each posted in its own input. */
    public const string OPTION_MULTIPLE = 'multiple';

    /** How many records at most, for a field holding several. */
    public const string OPTION_MAX_VALUES = 'max_values';

    /**
     * Whether the field offers the search box. Off, it shows the records it
     * holds and nothing else — they are put there by something other than
     * this field.
     */
    public const string OPTION_SEARCH = 'search';

    /**
     * The question asked before a record is taken out, for a list where
     * removing one is not a keystroke to be undone. `true` reads it from the
     * field's own translations, as a label does.
     */
    public const string OPTION_REMOVE_CONFIRM = 'remove_confirm';

    /**
     * What to show for the value the field opens with. Read from the record
     * itself when there is one; said here for a field handed a bare id.
     */
    public const string OPTION_SELECTED_TITLE = 'selected_title';

    public const string OPTION_SELECTED_SUBTITLE = 'selected_subtitle';

    public const string OPTION_SELECTED_ICON = 'selected_icon';

    public const string SEARCH_CONTEXT_DEFAULT = 'form_field';

    public function __construct(
        private readonly SearchableRegistry $registry,
        private readonly EntityPicker $picker,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault(self::OPTION_ENTITY_TYPE, null);
        $resolver->setAllowedTypes(self::OPTION_ENTITY_TYPE, ['null', 'string']);

        $resolver->setDefault(self::OPTION_CLASS, null);
        $resolver->setAllowedTypes(self::OPTION_CLASS, ['null', 'string']);

        $resolver->setDefault(self::OPTION_SEARCH_CONTEXT, self::SEARCH_CONTEXT_DEFAULT);
        $resolver->setAllowedTypes(self::OPTION_SEARCH_CONTEXT, 'string');

        $resolver->setDefault(self::OPTION_MULTIPLE, false);
        $resolver->setAllowedTypes(self::OPTION_MULTIPLE, 'bool');

        $resolver->setDefault(self::OPTION_MAX_VALUES, null);
        $resolver->setAllowedTypes(self::OPTION_MAX_VALUES, ['null', 'int']);

        $resolver->setDefault(self::OPTION_SEARCH, true);
        $resolver->setAllowedTypes(self::OPTION_SEARCH, 'bool');

        $resolver->setDefault(self::OPTION_REMOVE_CONFIRM, null);
        $resolver->setAllowedTypes(self::OPTION_REMOVE_CONFIRM, ['null', 'bool', 'string']);

        // A field holding several values is empty as a list, not as a string:
        // nothing posted for it means no record, not one record called ''.
        $resolver->setDefault(
            'empty_data',
            static fn (Options $options): array|string => $options[self::OPTION_MULTIPLE] ? [] : ''
        );

        // `TextType` has no placeholder — a text field shows none — and this
        // one is a search box, which has nothing else to show while empty.
        // `true` reads it from the field's own translations, as a label does.
        $resolver->setDefault('placeholder', null);
        $resolver->setAllowedTypes('placeholder', ['null', 'bool', 'string']);

        $resolver->setDefault('invalid_message', 'This record cannot be picked here.');

        foreach ([self::OPTION_SELECTED_TITLE, self::OPTION_SELECTED_SUBTITLE, self::OPTION_SELECTED_ICON] as $option) {
            $resolver->setDefault($option, null);
            $resolver->setAllowedTypes($option, ['null', 'string']);
        }
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        // The relation is read off the parent, which a builder does not know
        // yet: it is read when the field gets its data, which always comes
        // before anything is transformed.
        $target = null;

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use (&$target, $options): void {
                $target ??= $this->resolveTarget($event->getForm(), $options);
            }
        );

        $record = new EntitySearchInputTransformer(
            $this->picker,
            static function () use (&$target): SearchableEntity {
                return $target;
            },
            $options[self::OPTION_SEARCH_CONTEXT]
        );

        $builder->addModelTransformer(
            $options[self::OPTION_MULTIPLE]
                ? new EntitySearchInputListTransformer($record)
                : $record
        );

        if (null !== $options[self::OPTION_MAX_VALUES]) {
            // The cap the field draws is a comfort; this is the cap. A list
            // posted without the field — or with it, by someone sending more
            // inputs than it offered — is refused here.
            $builder->addEventListener(
                FormEvents::POST_SUBMIT,
                function (FormEvent $event) use ($options): void {
                    $this->guardMaxValues($event->getForm(), $event->getData(), $options[self::OPTION_MAX_VALUES]);
                }
            );
        }
    }

    /**
     * @param Collection<int, AbstractEntity>|array<AbstractEntity>|AbstractEntity|null $data
     */
    private function guardMaxValues(
        FormInterface $form,
        mixed $data,
        int $max
    ): void {
        $count = match (true) {
            $data instanceof Collection => $data->count(),
            is_array($data) => count($data),
            null === $data => 0,
            default => 1,
        };

        if ($count <= $max) {
            return;
        }

        $form->addError(
            new FormError(
                sprintf('This field holds at most %d records.', $max),
                'This field holds at most {{ limit }} records.',
                ['{{ limit }}' => $max]
            )
        );
    }

    public function buildView(
        FormView $view,
        FormInterface $form,
        array $options
    ): void {
        parent::buildView($view, $form, $options);

        $view->vars[self::OPTION_ENTITY_TYPE] = $this->resolveTarget($form, $options)->getType();
        $view->vars[self::OPTION_SEARCH_CONTEXT] = $options[self::OPTION_SEARCH_CONTEXT];
        $view->vars[self::OPTION_MULTIPLE] = $options[self::OPTION_MULTIPLE];
        $view->vars[self::OPTION_MAX_VALUES] = $options[self::OPTION_MAX_VALUES];
        $view->vars[self::OPTION_SEARCH] = $options[self::OPTION_SEARCH];
        $view->vars[self::OPTION_REMOVE_CONFIRM] = $options[self::OPTION_REMOVE_CONFIRM];

        $selected = [
            self::OPTION_SELECTED_TITLE => $options[self::OPTION_SELECTED_TITLE],
            self::OPTION_SELECTED_SUBTITLE => $options[self::OPTION_SELECTED_SUBTITLE],
            self::OPTION_SELECTED_ICON => $options[self::OPTION_SELECTED_ICON],
        ];

        // The record the field opens on says what it is called; the options
        // still have the last word.
        $data = $form->getData();
        if ($data instanceof AbstractEntity && ($result = $this->picker->describe($data))) {
            $selected[self::OPTION_SELECTED_TITLE] ??= $result->getTitle();
            $selected[self::OPTION_SELECTED_SUBTITLE] ??= $result->getSubtitle();
            $selected[self::OPTION_SELECTED_ICON] ??= $result->getIcon();
        }

        $view->vars = [...$view->vars, ...$selected];
        $view->vars['selected'] = $this->describeSelected($view, $form, $options);

        if ($options[self::OPTION_MULTIPLE]) {
            // One input per value, as any multi-valued field posts.
            $view->vars['full_name'] .= '[]';
        }
    }

    /**
     * What the field opens on, as the rows the person reads: one per record it
     * holds. A field handed bare identifiers has no record to read and says
     * what to show with the `selected_*` options — which only name one record,
     * so a list of them shows the identifiers themselves.
     *
     * @return list<array{id: string, title: string, subtitle: string|null, icon: string|null}>
     */
    private function describeSelected(
        FormView $view,
        FormInterface $form,
        array $options
    ): array {
        $data = $form->getData();
        $records = match (true) {
            $data instanceof AbstractEntity => [$data],
            $data instanceof Collection => $data->toArray(),
            is_array($data) => $data,
            default => [],
        };

        $rows = [];

        foreach ($records as $record) {
            if (! $record instanceof AbstractEntity) {
                continue;
            }

            $rows[] = $this->describeRecord($record, $this->picker->describe($record));
        }

        if ([] === $rows) {
            foreach (self::readIdentifiers($view->vars['value'] ?? null) as $id) {
                $rows[] = [
                    'id' => $id,
                    'title' => $options[self::OPTION_SELECTED_TITLE] ?? $id,
                    'subtitle' => $options[self::OPTION_SELECTED_SUBTITLE],
                    'icon' => $options[self::OPTION_SELECTED_ICON],
                ];
            }

            return $rows;
        }

        // The options have the last word on the one record a single field
        // holds; they have nothing to say about a list of them.
        if (! $options[self::OPTION_MULTIPLE]) {
            $rows[0]['title'] = $options[self::OPTION_SELECTED_TITLE] ?? $rows[0]['title'];
            $rows[0]['subtitle'] = $options[self::OPTION_SELECTED_SUBTITLE] ?? $rows[0]['subtitle'];
            $rows[0]['icon'] = $options[self::OPTION_SELECTED_ICON] ?? $rows[0]['icon'];
        }

        return $rows;
    }

    /**
     * @return array{id: string, title: string, subtitle: string|null, icon: string|null}
     */
    private function describeRecord(
        AbstractEntity $record,
        ?SearchResult $result
    ): array {
        $id = (string) $record->getId();

        return [
            'id' => $id,
            // A record of a class that is not findable says nothing about
            // itself: the identifier is all there is left to show.
            'title' => $result?->getTitle() ?? $id,
            'subtitle' => $result?->getSubtitle(),
            'icon' => $result?->getIcon(),
        ];
    }

    /**
     * @return list<string>
     */
    private static function readIdentifiers(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_values(
            array_map(
                static fn (mixed $id): string => (string) $id,
                array_filter(
                    $values,
                    static fn (mixed $id): bool => is_scalar($id) && '' !== (string) $id
                )
            )
        );
    }

    /**
     * The kind of record asked for: said by an option, or read from the
     * relation the field is bound to on its parent's class.
     */
    private function resolveTarget(
        FormInterface $form,
        array $options
    ): SearchableEntity {
        if (null !== $options[self::OPTION_ENTITY_TYPE]) {
            return $this->registry->get($options[self::OPTION_ENTITY_TYPE])
                ?? throw new LogicException(sprintf('No searchable entity answers to "%s".', $options[self::OPTION_ENTITY_TYPE]));
        }

        $className = $options[self::OPTION_CLASS] ?? $this->readRelation($form, $options[self::OPTION_MULTIPLE]);

        return $this->registry->getByClass($className)
            ?? throw new LogicException(sprintf('Field "%s" asks for %s, which carries no #[Searchable].', $form->getName(), $className));
    }

    /**
     * @param bool $multiple Whether the field holds several records, which the
     *                       relation it is bound to has to agree with
     *
     * @return class-string
     */
    private function readRelation(
        FormInterface $form,
        bool $multiple
    ): string {
        $parentClass = $form->getParent()?->getConfig()->getDataClass();
        $property = (string) $form->getPropertyPath();

        if (null === $parentClass || ! $form->getConfig()->getMapped()) {
            throw new LogicException(sprintf('Field "%s" is bound to no relation: say which kind of record it asks for, with `class` or `entity_type`.', $form->getName()));
        }

        $metadata = $this->entityManager->getClassMetadata($parentClass);

        if (! $metadata->hasAssociation($property)) {
            throw new LogicException(sprintf('%s::$%s is not a relation: say which kind of record the field asks for, with `class` or `entity_type`.', $parentClass, $property));
        }

        if ($metadata->isCollectionValuedAssociation($property) !== $multiple) {
            throw new LogicException(
                $multiple
                    ? sprintf('%s::$%s holds one record, and the field picks several: drop `multiple`.', $parentClass, $property)
                    : sprintf('%s::$%s holds several records, and the field picks one: pass `multiple`.', $parentClass, $property)
            );
        }

        return $metadata->getAssociationTargetClass($property);
    }
}

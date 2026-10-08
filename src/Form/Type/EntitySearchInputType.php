<?php

namespace Wexample\SymfonySearch\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Wexample\SymfonyForms\Form\Traits\FieldOptionsTrait;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonySearch\Class\SearchableEntity;
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

        $builder->addModelTransformer(
            new EntitySearchInputTransformer(
                $this->picker,
                static function () use (&$target): SearchableEntity {
                    return $target;
                },
                $options[self::OPTION_SEARCH_CONTEXT]
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

        $className = $options[self::OPTION_CLASS] ?? $this->readRelation($form);

        return $this->registry->getByClass($className)
            ?? throw new LogicException(sprintf('Field "%s" asks for %s, which carries no #[Searchable].', $form->getName(), $className));
    }

    /** @return class-string */
    private function readRelation(FormInterface $form): string
    {
        $parentClass = $form->getParent()?->getConfig()->getDataClass();
        $property = (string) $form->getPropertyPath();

        if (null === $parentClass || ! $form->getConfig()->getMapped()) {
            throw new LogicException(sprintf('Field "%s" is bound to no relation: say which kind of record it asks for, with `class` or `entity_type`.', $form->getName()));
        }

        $metadata = $this->entityManager->getClassMetadata($parentClass);

        if (! $metadata->hasAssociation($property)) {
            throw new LogicException(sprintf('%s::$%s is not a relation: say which kind of record the field asks for, with `class` or `entity_type`.', $parentClass, $property));
        }

        if ($metadata->isCollectionValuedAssociation($property)) {
            throw new LogicException(sprintf('%s::$%s holds several records, and the field picks one.', $parentClass, $property));
        }

        return $metadata->getAssociationTargetClass($property);
    }
}

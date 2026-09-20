<script>
import BaseField from '@wexample/symfony-design-system/components/bases/form-field/form-field.vue';
import SearchBox from '../../search/search-box.vue';
import Bar from '@wexample/symfony-design-system/components/bar/bar.vue';
import buildTranslatedBindings from '@wexample/symfony-design-system/js/Helper/TranslationHelper';

const translated = buildTranslatedBindings({
  resolvedClearLabel: [
    'clearLabel',
    'WexampleSymfonySearchBundle.vue.form.fields.entity-search-input::clear'
  ]
});

// A field whose value is a record, picked by searching for it.
//
// What the form submits is an id, in a hidden input like any other field; what
// the person sees is the search box until they pick, and the picked record
// afterwards. The box is told which kind to answer with, so a field asking for
// an invoice never offers a page.
export default {
  extends: BaseField,

  template: '#vue-template-wexample-symfony-search-bundle-vue-form-fields-entity-search-input',

  emits: ['update:modelValue'],

  components: {
    SearchBox,
    Bar
  },

  props: {
    ...translated.props,

    // The id of the picked record, which is what the form carries.
    modelValue: {
      type: String,
      default: ''
    },
    // Which kind of record this field asks for — an entity name.
    type: {
      type: String,
      default: null
    },
    context: {
      type: String,
      default: 'form_field'
    },
    placeholder: {
      type: String,
      default: ''
    },
    // What to show for a value the field opens with, the id alone saying
    // nothing to the person reading it.
    selectedTitle: {
      type: String,
      default: ''
    },
    selectedSubtitle: {
      type: String,
      default: ''
    },
    selectedIcon: {
      type: String,
      default: ''
    }
  },

  data() {
    return {
      picked: this.modelValue
        ? { title: this.selectedTitle, subtitle: this.selectedSubtitle, icon: this.selectedIcon }
        : null
    };
  },

  computed: {
    ...translated.computed,

    hasPicked() {
      return this.picked !== null;
    }
  },

  watch: {
    // Cleared from the outside — a form reset, a controller — the field goes
    // back to searching rather than showing a record it no longer carries.
    modelValue(value) {
      if (!value) {
        this.picked = null;
      }
    }
  },

  methods: {
    onSelect({ result }) {
      const read = (name) => {
        const value = typeof result.getDataValue === 'function'
          ? result.getDataValue(name)
          : result[name];

        return typeof value === 'string' ? value : '';
      };

      this.picked = {
        title: read('title'),
        subtitle: read('subtitle'),
        icon: read('icon')
      };

      this.$emit('update:modelValue', read('reference'));
    },

    clear() {
      this.picked = null;
      this.$emit('update:modelValue', '');
      this.$nextTick(() => this.$refs.searchBox?.$refs?.searchInput?.focus());
    },

    // Picking is not writing: what a value is spelled out into is the field's
    // own hidden input, and the box is left out of it.
    async writeValueAssisted(value) {
      this.$emit('update:modelValue', String(value ?? ''));
    }
  }
};
</script>

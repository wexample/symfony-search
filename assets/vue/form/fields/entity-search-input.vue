<script>
import FormField from '@wexample/symfony-design-system/components/_abstract/form-field/form-field.vue';
import SearchBox from '../../search/search-box.vue';
import Bar from '@wexample/symfony-design-system/components/bar/bar.vue';
import buildTranslatedBindings from '@wexample/symfony-design-system/js/Helper/TranslationHelper';

const translated = buildTranslatedBindings({
  resolvedClearLabel: [
    'clearLabel',
    'WexampleSymfonySearchBundle.vue.form.fields.entity-search-input::clear'
  ],
  resolvedRemoveLabel: [
    'removeLabel',
    'WexampleSymfonySearchBundle.vue.form.fields.entity-search-input::remove'
  ],
  resolvedEmptyLabel: [
    'emptyLabel',
    'WexampleSymfonySearchBundle.vue.form.fields.entity-search-input::empty'
  ]
});

// A field whose value is a record, picked by searching for it.
//
// What the form submits is an id, in a hidden input like any other field; what
// the person sees is the search box until they pick, and the picked record
// afterwards. The box is told which kind to answer with, so a field asking for
// an invoice never offers a page.
//
// `multiple` makes it several records: one hidden input per value, one row per
// record, and the box still there under them until the cap is reached.
// `search` turned off leaves the rows alone, for a relation filled by
// something other than this field.
export default {
  extends: FormField,

  template: '#vue-template-wexample-symfony-search-bundle-vue-form-fields-entity-search-input',

  emits: ['update:modelValue'],

  components: {
    SearchBox,
    Bar
  },

  props: {
    ...translated.props,

    // The id of the picked record, which is what the form carries. A list of
    // them where the field holds several.
    modelValue: {
      type: [String, Array],
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
    // Several records rather than one.
    multiple: {
      type: Boolean,
      default: false
    },
    // How many at most, for a field holding several. Null is no cap.
    maxValues: {
      type: Number,
      default: null
    },
    // Whether the field offers the search box at all.
    search: {
      type: Boolean,
      default: true
    },
    // What to ask before a record is taken out. Empty asks nothing.
    removeConfirm: {
      type: String,
      default: ''
    },
    // What the field opens on: one row per record it holds, each saying what
    // the person reads — the id alone saying nothing to them.
    selected: {
      type: Array,
      default: () => []
    }
  },

  data() {
    return {
      // The rows shown, which are also what is posted. Held here and not only
      // read from the props: drawn from Twig the field has no parent binding
      // it, and an emitted value would reach nothing — the hidden inputs would
      // post what the page opened with.
      picked: this.selected.map((row) => ({ ...row })),
      // The id posted by a field holding one record.
      value: Array.isArray(this.modelValue) ? '' : (this.modelValue || '')
    };
  },

  computed: {
    ...translated.computed,

    hasPicked() {
      return this.picked.length > 0;
    },

    pickedIds() {
      return this.picked.map((row) => row.id);
    },

    removeIconHtml() {
      return this.app.getServiceOrFail('icon').icon('ph:bold/x');
    },

    isAtCap() {
      return this.maxValues !== null && this.picked.length >= this.maxValues;
    },

    // Gone once a single field is filled — the record stands where the box
    // stood — and once a list is full: a box that refuses everything it is
    // handed is worse than no box, and it comes back as soon as a row leaves.
    hasSearch() {
      return this.search && !this.isAtCap && (this.multiple || !this.hasPicked);
    }
  },

  watch: {
    // Cleared from the outside — a form reset, a controller — the field goes
    // back to searching rather than showing records it no longer carries.
    modelValue(value) {
      const ids = Array.isArray(value) ? value : (value ? [value] : []);

      this.value = Array.isArray(value) ? '' : (value || '');

      if (ids.length === 0) {
        this.picked = [];
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

      const row = {
        id: read('reference'),
        title: read('title'),
        subtitle: read('subtitle'),
        icon: read('icon')
      };

      // The question has been answered: the box goes back to empty, rather
      // than keeping the rows of the record that just left it for the list.
      this.$refs.searchBox?.reset();

      if (!this.multiple) {
        this.picked = [row];
        this.value = row.id;
        this.notifyChange();

        return;
      }

      // The same record twice is still one record. Said nowhere: the row is
      // already there for the person to see, and the server guards the list
      // again anyway.
      if (this.isAtCap || this.pickedIds.includes(row.id)) {
        return;
      }

      this.picked = [...this.picked, row];
      this.notifyChange();
    },

    async remove(index) {
      if (this.removeConfirm && !(await this.askRemoveConfirmation())) {
        return;
      }

      this.picked = this.picked.filter((row, position) => position !== index);
      this.value = '';
      this.notifyChange();

      if (!this.multiple) {
        this.$nextTick(() => this.$refs.searchBox?.$refs?.searchInput?.focus());
      }
    },

    async askRemoveConfirmation() {
      const confirmService = this.app.services.confirm;

      if (!confirmService) {
        return window.confirm(this.removeConfirm);
      }

      return await confirmService.confirm({
        message: this.removeConfirm,
        preset: 'yes_no'
      }) === 'yes';
    },

    removeAriaLabel(row) {
      return this.multiple ? `${this.resolvedRemoveLabel} ${row.title}` : this.resolvedClearLabel;
    },

    // Picking is not writing: what a value is spelled out into is the field's
    // own hidden inputs, and the box is left out of it. A record named by its
    // id alone is all an assisted write can show — the record it stands for
    // is what the server reads back from it.
    async writeValueAssisted(value) {
      const ids = (Array.isArray(value) ? value : String(value ?? '').split(','))
        .map((id) => String(id).trim())
        .filter((id) => id !== '');

      this.picked = (this.multiple ? ids : ids.slice(0, 1))
        .map((id) => ({ id, title: id, subtitle: '', icon: '' }));
      this.value = this.multiple ? '' : (this.picked[0]?.id ?? '');
      this.notifyChange();
    },

    notifyChange() {
      this.$emit('update:modelValue', this.multiple ? this.pickedIds : this.value);

      // The hidden inputs are filled by hand, and nothing raises `change` for
      // what a program writes. The form hears of the new value only if the
      // field says so — which is what a dirty state and `submit_on_change`
      // listen for. Once the inputs hold it: a form sent on that event reads
      // the DOM, not this component.
      this.$nextTick(() => {
        this.$el.dispatchEvent(new Event('change', { bubbles: true }));
      });
    }
  }
};
</script>

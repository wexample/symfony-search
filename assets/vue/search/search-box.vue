<script>
import buildTranslatedBindings from "@wexample/symfony-design-system/js/Helper/TranslationHelper";
import FocusableVueMixin from "@wexample/symfony-design-system/js/Vue/FocusableVueMixin";
import WithRovingFocusKeyboardVueMixin from "@wexample/symfony-design-system/js/Vue/WithRovingFocusKeyboardVueMixin";
import KeyboardService from "@wexample/symfony-loader/js/Services/KeyboardService";
import OverlayService from "@wexample/symfony-loader/js/Services/OverlayService";
import { stringToKebab } from '@wexample/js-helpers/Helper/String';
import SearchResult from "./search-result.vue";
import Spinner from "@wexample/symfony-design-system/vue/partials/spinner.vue";

const translated = buildTranslatedBindings({
  resolvedPlaceholder: [
    'placeholder',
    'WexampleSymfonySearchBundle.vue.search.search-box::placeholder'
  ],
  resolvedEmptyLabel: [
    'emptyLabel',
    'WexampleSymfonySearchBundle.vue.search.search-box::empty'
  ],
  resolvedPendingLabel: [
    'pendingLabel',
    'WexampleSymfonySearchBundle.vue.search.search-box::pending'
  ]
});

const RESULT_COMPONENT_DEFAULT = 'search-result';
const RESULT_COMPONENT_PREFIX = 'search-result-';

// One field, every kind of result.
//
// The box asks a repository — `searchResult` by default, which is the entity
// wexample/symfony-search answers with — and draws one row per result. Which
// row is decided by the result's type: a component registered under
// `search-result-<type>` takes it, the default row takes the rest. A
// subclass adds those components and nothing else; the box knows no type.
//
// Arrows, Home, End and Enter walk the rows through the keyboard service, so
// that a dropdown and a modal open at once never both answer the same key.
// Escape closes. The mouse is handled by the overlay service, which is what
// says a click landed outside.
export default {
  template: '#vue-template-wexample-symfony-search-bundle-vue-search-search-box',

  mixins: [
    FocusableVueMixin,
    WithRovingFocusKeyboardVueMixin
  ],

  components: {
    SearchResult,
    Spinner
  },

  emits: ['select'],

  props: {
    ...translated.props,

    // The entity repository answering, by the name the api client knows it under.
    repository: {
      type: String,
      default: 'searchResult'
    },
    context: {
      type: String,
      default: 'default'
    },
    // Restricts the answer to one kind — an entity name, or a provider key.
    // Null asks everyone, which is what a header does.
    type: {
      type: String,
      default: null
    },
    // Whether a row opens what it names. False where picking is the point.
    linkResults: {
      type: Boolean,
      default: true
    },
    length: {
      type: Number,
      default: 8
    },
    // One letter matches everything and means nothing.
    minLength: {
      type: Number,
      default: 2
    },
    // Typing is faster than the round trip: only the pause asks.
    debounceMs: {
      type: Number,
      default: 200
    }
  },

  data() {
    return {
      terms: '',
      results: [],
      isOpen: false,
      isLoading: false,
      // Answers can land out of order: only the last query asked for may paint.
      lastRequestId: 0,
      debounceTimer: null,
      // Read by the overlay service: a dropdown dims nothing behind it.
      overlayUseBackdrop: false
    };
  },

  computed: {
    ...translated.computed,

    hasItems() {
      return this.results.length > 0;
    },

    searchIconHtml() {
      return this.app.getServiceOrFail('icon').icon('ph:bold/magnifying-glass');
    }
  },

  mounted() {
    this.app.ready(() => {
      this.getOverlayService().register(this);
    });
  },

  beforeUnmount() {
    clearTimeout(this.debounceTimer);
    this.getOverlayService().unregister(this);
  },

  methods: {
    // --- Roving focus: which elements the arrows walk.

    rovingItemSelector() {
      return '[data-search-result]';
    },

    rovingHasItems() {
      return this.isOpen && this.hasItems;
    },

    // The roving bindings, plus Escape: the box is the only one of the two
    // mixins to know it can be closed.
    keyboardBindings() {
      return [
        ...WithRovingFocusKeyboardVueMixin.methods.keyboardBindings.call(this),
        {
          key: KeyboardService.KEY_ESCAPE,
          callback: this.onEscapeKey,
          options: {
            enabled: () => this.isOpen
          }
        }
      ];
    },

    onEscapeKey() {
      this.close();
      this.getSearchInputElement()?.focus();

      return true;
    },

    // --- Overlay: what the service asks of anything that opens over the page.

    overlayIsOpen() {
      return this.isOpen;
    },

    overlayGetElement() {
      return this.getFocusableRootElement();
    },

    overlayGetFocusTarget() {
      return this.getSearchInputElement();
    },

    overlayOnClickOutside() {
      this.close();
    },

    getOverlayService() {
      return this.app.getServiceOrFail(OverlayService);
    },

    // --- Asking.

    onInput(event) {
      this.terms = event.target.value;
      clearTimeout(this.debounceTimer);

      if (this.terms.trim().length < this.minLength) {
        this.close();
        return;
      }

      this.debounceTimer = setTimeout(() => this.search(), this.debounceMs);
    },

    // Coming back to a field that still holds a question reopens its answer.
    onFocus() {
      if (this.hasItems && this.terms.trim().length >= this.minLength) {
        this.open();
      }
    },

    getRepository() {
      return this.app.getService('api').client.getRepository(this.repository);
    },

    getFetchParams() {
      const query = {
        search: this.terms.trim(),
        context: this.context
      };

      if (this.type) {
        query.type = this.type;
      }

      return { query, length: this.length };
    },

    async search() {
      const requestId = ++this.lastRequestId;

      this.open();
      this.isLoading = true;

      try {
        const results = await this.getRepository().fetchList(this.getFetchParams());

        if (requestId === this.lastRequestId) {
          this.results = results;
        }
      } catch (error) {
        if (requestId === this.lastRequestId) {
          this.results = [];
        }

        throw error;
      } finally {
        if (requestId === this.lastRequestId) {
          this.isLoading = false;
        }
      }
    },

    // --- Drawing.

    resultKey(result) {
      return result?.id ?? result;
    },

    resultType(result) {
      const value = typeof result.getDataValue === 'function'
        ? result.getDataValue('type')
        : result.type;

      return typeof value === 'string' ? value : '';
    },

    // The component named after the type when one is registered — locally by
    // a subclass, or globally by the app — and the default row otherwise.
    resultComponent(result) {
      const name = RESULT_COMPONENT_PREFIX + stringToKebab(this.resultType(result));

      return this.hasComponent(name) ? name : RESULT_COMPONENT_DEFAULT;
    },

    hasComponent(name) {
      const local = this.$options.components || {};

      if (local[name]) {
        return true;
      }

      // Declared in PascalCase, registered in kebab: both spellings are the
      // same component.
      const pascal = name.replace(/(^|-)([a-z])/g, (match, sep, letter) => letter.toUpperCase());

      if (local[pascal]) {
        return true;
      }

      return Boolean(this.$.appContext.components[name]);
    },

    onSelect(payload) {
      this.$emit('select', payload);
    },

    open() {
      if (this.isOpen) {
        return;
      }

      this.isOpen = true;
      this.getOverlayService().setActive(this);
    },

    close() {
      if (!this.isOpen) {
        return;
      }

      this.isOpen = false;
      this.getOverlayService().clearActive(this);
    }
  }
};
</script>

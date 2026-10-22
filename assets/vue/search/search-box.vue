<script>
import buildTranslatedBindings from "@wexample/symfony-design-system/js/Helper/TranslationHelper";
import FocusableVueMixin from "@wexample/symfony-design-system/js/Vue/FocusableVueMixin";
import WithRovingFocusKeyboardVueMixin from "@wexample/symfony-design-system/js/Vue/WithRovingFocusKeyboardVueMixin";
import KeyboardService from "@wexample/symfony-loader/js/Services/KeyboardService";
import OverlayService from "@wexample/symfony-loader/js/Services/OverlayService";
import { floatingPlace } from "@wexample/symfony-design-system/js/Helper/FloatingHelper";
import { stringToKebab } from '@wexample/js-helpers/Helper/String';
import SearchResult from "./search-result.vue";
import Spinner from "@wexample/symfony-design-system/components/spinner/spinner.vue";

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
// Escape closes, and closes again onto the question the field was entered
// with. The mouse is handled by the overlay service, which is what says a
// click landed outside — so the dropdown outlives the field losing focus, and
// a click on a row is never lost on the way.
//
// The dropdown opens in the top layer, out of whatever clips or scrolls around
// the field, placed by the design system's floating helper: under the field, or
// over it when the window ends first.
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

    // The id the field carries, for a label pointing at it.
    inputId: {
      type: String,
      default: ''
    },
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
      // What the question was when the field was entered, which Escape puts
      // back once the dropdown is closed.
      termsAtFocus: '',
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

  watch: {
    isOpen(open) {
      if (open) {
        // Shown before it is measured: a box with no size is placed nowhere.
        this.$nextTick(() => this.showDropdown());

        return;
      }

      this.hideDropdown();
    },

    // What it holds decides how tall it is, and how tall it is decides which
    // side of the field it stands on.
    results() {
      this.$nextTick(() => this.placeDropdown());
    },

    isLoading() {
      this.$nextTick(() => this.placeDropdown());
    }
  },

  mounted() {
    this.app.ready(() => {
      this.getOverlayService().register(this);
    });
  },

  beforeUnmount() {
    clearTimeout(this.debounceTimer);
    this.stopFollowingViewport();
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
            // Only what the box has something to answer: once it is closed and
            // holds the question it was entered with, Escape belongs to
            // whatever stands behind it — a modal holding the field.
            enabled: () => this.isOpen || this.terms !== this.termsAtFocus
          }
        }
      ];
    },

    onEscapeKey() {
      if (this.isOpen) {
        this.close();
        this.getSearchInputElement()?.focus();

        return true;
      }

      // Pressed again on a closed box: the question goes back to the one the
      // field was entered with — nothing, for a field entered empty.
      this.terms = this.termsAtFocus;
      this.results = [];

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
      this.termsAtFocus = this.terms;

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

    // The question has been answered. Called by whatever took the answer — a
    // field that keeps the box to be handed the next one: left open, it would
    // offer the rows of a question nobody is asking any more.
    reset() {
      clearTimeout(this.debounceTimer);
      this.terms = '';
      this.termsAtFocus = '';
      this.results = [];
      this.close();
    },

    // --- Where the dropdown stands.

    showDropdown() {
      const dropdown = this.$refs.dropdown;

      if (dropdown && !dropdown.matches(':popover-open')) {
        dropdown.showPopover?.();
      }

      this.placeDropdown();
      window.addEventListener('scroll', this.onViewportChange, true);
      window.addEventListener('resize', this.onViewportChange);
    },

    hideDropdown() {
      this.stopFollowingViewport();

      const dropdown = this.$refs.dropdown;

      if (dropdown?.matches(':popover-open')) {
        dropdown.hidePopover();
      }
    },

    stopFollowingViewport() {
      window.removeEventListener('scroll', this.onViewportChange, true);
      window.removeEventListener('resize', this.onViewportChange);
    },

    onViewportChange(event) {
      // The dropdown scrolling its own rows moves the field nowhere.
      if (event?.target instanceof Node && this.$refs.dropdown?.contains(event.target)) {
        return;
      }

      this.placeDropdown();
    },

    // Beside the field and never narrower than it, by the same helper that
    // places the design system's select, its tooltips and its tours: the box
    // measures nothing of its own.
    placeDropdown() {
      const dropdown = this.$refs.dropdown;
      const field = this.$refs.focusableRoot;

      if (!this.isOpen || !dropdown || !field) {
        return;
      }

      dropdown.style.minWidth = `${field.offsetWidth}px`;
      floatingPlace(field, dropdown, { placement: 'bottom', align: 'start', offset: 4 });
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

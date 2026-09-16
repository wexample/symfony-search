import Component from '@wexample/symfony-loader/js/Class/Component';
import OverlayService from '@wexample/symfony-loader/js/Services/OverlayService';
import KeyboardService from '@wexample/symfony-loader/js/Services/KeyboardService';
import ApiService from '@wexample/symfony-loader/js/Services/ApiService';
import type AbstractApiEntity from '@wexample/js-api/Common/AbstractApiEntity';
import type SearchResultRepository from '../Repository/SearchResultRepository.js';

/** Typing is faster than the round trip: only the pause asks. */
const DEBOUNCE_MS = 200;

/** One letter matches everything and means nothing. */
const TERMS_MIN_LENGTH = 2;

const RESULTS_LENGTH_DEFAULT = 8;

const CONTEXT_DEFAULT = 'header';

/**
 * One field, every kind of result.
 *
 * What comes back is a collection of `SearchResult` entities like any other, so
 * the repository generated from the PHP class is what fetches them — this
 * component builds no url and reads no envelope. A result carries where it
 * goes, so it has nothing to decide about what a click means either.
 */
export default class extends Component {
  public overlayUseBackdrop = false;

  private inputEl?: HTMLInputElement;
  private dropdownEl?: HTMLElement;
  private listEl?: HTMLElement;
  private emptyEl?: HTMLElement;
  private pendingEl?: HTMLElement;
  private overlayService?: OverlayService;
  private keyboardService?: KeyboardService;

  private debounce?: ReturnType<typeof setTimeout>;
  // Answers can land out of order: only the last query asked for may paint.
  private lastRequestId = 0;

  protected async activateListeners(): Promise<void> {
    this.inputEl = this.el.querySelector('.search-bar--input') as HTMLInputElement;
    this.dropdownEl = this.el.querySelector('.search-bar--dropdown') as HTMLElement;
    this.listEl = this.el.querySelector('.search-bar--list') as HTMLElement;
    this.emptyEl = this.el.querySelector('.search-bar--empty') as HTMLElement;
    this.pendingEl = this.el.querySelector('.search-bar--pending') as HTMLElement;

    this.overlayService = this.app.getServiceOrFail(OverlayService) as OverlayService;
    this.keyboardService = this.app.getServiceOrFail(KeyboardService) as KeyboardService;

    this.overlayService.register(this);

    this.inputEl?.addEventListener('input', this.onInput);
    this.listEl?.addEventListener('click', this.onResultClick);

    this.keyboardService.registerKeyDown(this, KeyboardService.KEY_ESCAPE, () => {
      if (this.overlayIsOpen()) {
        this.close();
        return true;
      }
      return false;
    });
  }

  protected async deactivateListeners(): Promise<void> {
    this.overlayService?.unregister(this);
    this.keyboardService?.unregisterOwner(this);

    this.inputEl?.removeEventListener('input', this.onInput);
    this.listEl?.removeEventListener('click', this.onResultClick);

    clearTimeout(this.debounce);
  }

  public overlayIsOpen(): boolean {
    return this.dropdownEl ? !this.dropdownEl.hidden : false;
  }

  public overlayGetElement(): HTMLElement | null {
    return this.dropdownEl || null;
  }

  public overlayGetFocusTarget(): HTMLElement | null {
    return this.inputEl || null;
  }

  public overlayOnClickOutside(_event: MouseEvent): void {
    this.close();
  }

  private onInput = (): void => {
    clearTimeout(this.debounce);

    const terms = this.inputEl?.value.trim() ?? '';

    if (terms.length < TERMS_MIN_LENGTH) {
      this.close();
      return;
    }

    this.debounce = setTimeout(() => void this.search(terms), DEBOUNCE_MS);
  };

  private async search(terms: string): Promise<void> {
    const requestId = ++this.lastRequestId;

    this.open();
    this.setPending(true);

    try {
      const results = await this.getRepository().fetchList({
        query: {
          search: terms,
          context: this.el.dataset.context ?? CONTEXT_DEFAULT,
        },
        length: Number(this.el.dataset.length ?? RESULTS_LENGTH_DEFAULT),
      });

      if (requestId === this.lastRequestId) {
        this.render(results);
      }
    } catch (error) {
      if (requestId === this.lastRequestId) {
        this.render([]);
      }

      throw error;
    }
  }

  private getRepository(): SearchResultRepository {
    // Through the service rather than the render-node method it also registers:
    // the same client either way, but this one is typed.
    const apiService = this.app.getServiceOrFail(ApiService) as ApiService;

    return apiService.getClient().getRepository('searchResult') as SearchResultRepository;
  }

  private render(results: AbstractApiEntity[]): void {
    if (!this.listEl) {
      return;
    }

    this.listEl.replaceChildren(...results.map((result) => this.renderResult(result)));
    this.setPending(false);

    if (this.emptyEl) {
      this.emptyEl.hidden = results.length > 0;
    }
  }

  private renderResult(result: AbstractApiEntity): HTMLElement {
    const item = document.createElement('li');
    item.className = 'search-bar--result';
    item.setAttribute('role', 'option');
    item.dataset.url = this.readString(result, 'url');
    item.dataset.type = this.readString(result, 'type');

    // A result whose kind knows no route carries no url. It is still an answer
    // and stays in the list — it just does not pretend to be clickable.
    if (!item.dataset.url) {
      item.classList.add('search-bar--result--flat');
    }

    item.append(
      this.renderPart('search-bar--result-title', this.readString(result, 'title')),
      this.renderPart('search-bar--result-type', item.dataset.type)
    );

    const subtitle = this.readString(result, 'subtitle');

    if (subtitle) {
      item.append(this.renderPart('search-bar--result-subtitle', subtitle));
    }

    return item;
  }

  private renderPart(className: string, text: string): HTMLElement {
    const part = document.createElement('span');
    part.className = className;
    part.textContent = text;

    return part;
  }

  /**
   * Read through the entity's own accessor: the generated class declares the
   * schema, not the fields, so the values live in its data rather than on it.
   */
  private readString(result: AbstractApiEntity, name: string): string {
    const value = result.getDataValue(name);

    return typeof value === 'string' ? value : '';
  }

  private setPending(pending: boolean): void {
    if (this.pendingEl) {
      this.pendingEl.hidden = !pending;
    }

    if (pending && this.emptyEl) {
      this.emptyEl.hidden = true;
    }
  }

  private onResultClick = (event: Event): void => {
    const url = (event.target as HTMLElement).closest<HTMLElement>('.search-bar--result')?.dataset
      .url;

    if (url) {
      window.location.href = url;
    }
  };

  private open(): void {
    if (!this.dropdownEl || this.overlayIsOpen()) {
      return;
    }

    this.dropdownEl.hidden = false;
    this.inputEl?.setAttribute('aria-expanded', 'true');
    this.overlayService?.setActive(this);
  }

  private close(): void {
    if (!this.dropdownEl) {
      return;
    }

    this.dropdownEl.hidden = true;
    this.inputEl?.setAttribute('aria-expanded', 'false');
    this.listEl?.replaceChildren();
    this.setPending(false);

    if (this.emptyEl) {
      this.emptyEl.hidden = true;
    }

    this.overlayService?.clearActive(this);
  }
}

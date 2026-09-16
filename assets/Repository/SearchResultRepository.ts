import AbstractApiRepository from '@wexample/js-api/Common/AbstractApiRepository';
import SearchResult from '../Entity/SearchResult.js';

export default class SearchResultRepository extends AbstractApiRepository<SearchResult> {
  static getEntityType() {
    return SearchResult;
  }
}

import AbstractApiRepository from '@wexample/js-api-entity/Common/AbstractApiRepository';
import SearchResult from '../Entity/SearchResult.js';

export default class SearchResultRepository extends AbstractApiRepository<SearchResult> {
  static getEntityType() {
    return SearchResult;
  }
}

import AbstractApiEntity from '@wexample/js-api/Common/AbstractApiEntity';
import schema from '../data/entity/search_result.json';

export default class SearchResult extends AbstractApiEntity {
  static readonly entityName = 'searchResult';

  static retrieveEntitySchema() {
    return schema;
  }
}

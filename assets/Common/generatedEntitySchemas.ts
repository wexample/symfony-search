import searchResult from '../data/entity/search_result.json';

type EntitySchema = { name: string };

export default function getGeneratedEntitySchemas(): Record<string, EntitySchema> {
  return {
    [searchResult.name]: searchResult,
  };
}

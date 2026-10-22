import AbstractApiRepository from '@wexample/js-api-entity/Common/AbstractApiRepository';
import TunnelSessionVariable from '../Entity/TunnelSessionVariable.js';

export default class TunnelSessionVariableRepository extends AbstractApiRepository<TunnelSessionVariable> {
  static getEntityType() {
    return TunnelSessionVariable;
  }
}

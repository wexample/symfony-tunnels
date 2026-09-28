import AbstractApiEntity from '@wexample/js-api-entity/Common/AbstractApiEntity';
import schema from '../data/entity/tunnel_session.json';

export default class TunnelSession extends AbstractApiEntity {
  static readonly entityName = 'tunnelSession';

  static retrieveEntitySchema() {
    return schema;
  }
}

import AbstractApiEntity from '@wexample/js-api/Common/AbstractApiEntity';
import schema from '../data/entity/tunnel_session_variable.json';

export default class TunnelSessionVariable extends AbstractApiEntity {
  static readonly entityName = 'tunnelSessionVariable';

  static retrieveEntitySchema() {
    return schema;
  }
}

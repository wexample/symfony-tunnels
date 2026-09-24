import AbstractApiRepository from '@wexample/js-api/Common/AbstractApiRepository';
import TunnelSession from '../Entity/TunnelSession.js';

export default class TunnelSessionRepository extends AbstractApiRepository<TunnelSession> {
  static getEntityType() {
    return TunnelSession;
  }
}

import tunnelSession from '../data/entity/tunnel_session.json';
import tunnelSessionVariable from '../data/entity/tunnel_session_variable.json';

type EntitySchema = { name: string };

export default function getGeneratedEntitySchemas(): Record<string, EntitySchema> {
  return {
    [tunnelSession.name]: tunnelSession,
    [tunnelSessionVariable.name]: tunnelSessionVariable,
  };
}

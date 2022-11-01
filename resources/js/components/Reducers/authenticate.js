export default function(state = [], action) {
  switch (action.type) {
    case 'LOGIN':
      return [
        ...state,
        {
          id: action.id,
          user: action.text,
          loggedIn: true
        }
      ]
    case 'LOGOUT':
      return [
        ...state,
        {
          id: 0,
          user: '',
          loggedIn: false
        }
      ]
    default:
      return state
  }
}
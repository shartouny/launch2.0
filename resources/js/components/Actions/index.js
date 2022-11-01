export const login = text => ({
  type: 'LOGIN',
  text
});
export const addSwatches = payload => ({
  type: 'ADD_SWATCH',
  payload
});
export const swapColor = id => ({
    type: 'SWAP_COLOR',
    id
});
export const onCheckSelect = id => ({
  type: 'SELECT_COLOR',
  id
});

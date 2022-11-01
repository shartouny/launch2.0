const designSettings = (state = [], action) => {
  switch (action.type) {
    case 'ADD_SWATCHES':

      return [
        ...state,
        {
          id: action.id,
          color:action.color,
          image:action.image,
          label: action.label,
          active: action.active
        }
      ];
    case 'SWAP_COLOR':

      return state.map(swatch =>
          !swatch.active
          (swatch.id === action.id)
              ? {...swatch, active: !swatch.active}
              : swatch
      );
    case 'SELECT_COLOR':

      return [
          ...state,
        {
          id: action.id,
        }
      ];
    default:
      return state
  }
};

export default designSettings

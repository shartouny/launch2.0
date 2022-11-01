

// returns the query string parameters as an object
export const getQueryParameters = () => {
  let search = location.search.substring(1);
  if(search.length == 0) {
    // no query parameters
    return null;
  }
  return parseQueryString(search);
}

export const parseQueryString = (query) => {
  if(query && query.length > 0){
    const params = new URLSearchParams(query);
    let obj = {};
    for (let p of params) {
      obj[p[0]] = p[1];
    }
    return obj;
  } else {
    return null;
  }
}

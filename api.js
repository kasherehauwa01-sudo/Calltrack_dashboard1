(function(){
  const API_BASE='/vr/calltrack/api';
  const ENDPOINTS={
    calls:`${API_BASE}/get_calls.php`,
    dashboard:`${API_BASE}/dashboard.php`,
    deleteCalls:`${API_BASE}/delete_calls.php`,
    deleteCall:`${API_BASE}/delete_call.php`,
    personalContacts:`${API_BASE}/get_personal_contacts.php`
  };

  function buildQuery(filters={}){
    const params=new URLSearchParams();
    ['manager','phone','user_phone','date_from','date_to'].forEach((key)=>{
      const value=filters[key];
      if(value!==undefined&&value!==null&&String(value).trim()!=='') params.set(key,value);
    });
    const query=params.toString();
    return query?`?${query}`:'';
  }

  async function requestJson(url, options){
    const response=await fetch(url, options);
    if(!response.ok) throw new Error(`API ${response.status}: ${url}`);
    const text=await response.text();
    if(!text.trim()) return null;
    try{return JSON.parse(text);}catch(error){throw new Error(`Некорректный JSON от API: ${url}`);}
  }

  function unwrapList(payload){
    if(Array.isArray(payload)) return payload;
    if(Array.isArray(payload?.data)) return payload.data;
    if(Array.isArray(payload?.calls)) return payload.calls;
    if(payload?.status==='error') throw new Error(payload.message||'API_ERROR');
    return [];
  }

  async function loadCalls(filters={}){
    return unwrapList(await requestJson(`${ENDPOINTS.calls}${buildQuery(filters)}`));
  }

  async function loadDashboard(filters={}){
    return requestJson(`${ENDPOINTS.dashboard}${buildQuery(filters)}`);
  }

  async function loadPersonalContacts(filters={}){
    return unwrapList(await requestJson(`${ENDPOINTS.personalContacts}${buildQuery(filters)}`));
  }

  function isDeleteSuccess(data){
    if(data===null) return true;
    if(Array.isArray(data)) return false;
    if(data?.status==='success'||data?.ok===true||data?.success===true) return true;
    return Number(data?.deleted||data?.affected||data?.affected_rows||0)>0;
  }

  async function deleteCalls(records=[]){
    const ids=[...new Set(records.map((record)=>record.sqlId||record.sql_id||record.ID).filter(Boolean).map(String))];
    const callIds=[...new Set(records.map((record)=>record.callId||record.call_id||record.id).filter(Boolean).map(String))];
    if(!ids.length&&!callIds.length) throw new Error('Не найдены id выбранных строк для удаления');
    const payload={ids,call_ids:callIds,id:ids[0]||'',call_id:callIds[0]||''};
    const json={headers:{'Content-Type':'application/json'}};
    const query=new URLSearchParams();
    if(ids.length){query.set('ids',ids.join(','));query.set('id',ids[0]);}
    if(callIds.length){query.set('call_ids',callIds.join(','));query.set('call_id',callIds[0]);}
    const queryString=query.toString();
    const form=new URLSearchParams({action:'delete',...Object.fromEntries(query.entries())});
    const requests=[
      [ENDPOINTS.calls,{method:'POST',...json,body:JSON.stringify({action:'delete',...payload})}],
      [ENDPOINTS.calls,{method:'POST',...json,body:JSON.stringify({action:'delete_call',...payload})}],
      [ENDPOINTS.calls,{method:'POST',...json,body:JSON.stringify({action:'delete_calls',...payload})}],
      [ENDPOINTS.calls,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:form.toString()}],
      [`${ENDPOINTS.calls}?action=delete${queryString?`&${queryString}`:''}`,{method:'POST'}],
      [`${ENDPOINTS.calls}?action=delete${queryString?`&${queryString}`:''}`,{method:'DELETE'}],
      [ENDPOINTS.calls,{method:'DELETE',...json,body:JSON.stringify(payload)}],
      [ENDPOINTS.deleteCalls,{method:'POST',...json,body:JSON.stringify(payload)}],
      [ENDPOINTS.deleteCall,{method:'POST',...json,body:JSON.stringify(payload)}],
      [`${ENDPOINTS.deleteCalls}${queryString?`?${queryString}`:''}`,{method:'DELETE'}]
    ];
    const errors=[];
    for(const [url,options] of requests){
      try{
        const data=await requestJson(url,options);
        if(data?.status==='error') throw new Error(data.message||'DELETE_ERROR');
        if(!isDeleteSuccess(data)) throw new Error('DELETE_NOT_CONFIRMED');
        return data;
      }catch(error){
        errors.push(`${url}: ${error.message}`);
      }
    }
    throw new Error(`SQL API не подтвердил удаление. ${errors[0]||'DELETE_ERROR'}`);
  }

  window.calltrackApi=window.calltrackApi||{};
  window.calltrackApi.endpoints={...ENDPOINTS,...(window.calltrackApi.endpoints||{})};
  window.calltrackApi.loadCalls=loadCalls;
  window.calltrackApi.loadDashboard=loadDashboard;
  window.calltrackApi.loadPersonalContacts=loadPersonalContacts;
  window.calltrackApi.deleteCalls=deleteCalls;
})();

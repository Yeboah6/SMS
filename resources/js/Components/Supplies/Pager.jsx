import Pagination from '../Pagination';

export default function Pager({ links, from, to, total }) {
    return <Pagination links={links} from={from} to={to} total={total} />;
}
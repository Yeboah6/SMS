import LookupIndex from '../../Components/Staffs/LookupIndex';

export default function DepartmentsIndex({ items, unassigned }) {
    return <LookupIndex title="Departments" description="The departments staff are filed under, such as Administration, Science or Finance." noun="department" baseUrl="/departments" items={items} unassigned={unassigned} />;
}